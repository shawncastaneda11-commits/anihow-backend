<?php

namespace Tests\Feature;

use App\Actions\Listings\BackfillOpeningHarvestRecords;
use App\Actions\Privacy\ExportOwnDataAction;
use App\Enums\CancellationReason;
use App\Enums\HarvestRecordKind;
use App\Enums\ListingStatus;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\ProductCategory;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Models\HarvestRecord;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\User;
use App\Support\HarvestInput;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class HarvestStockTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_existing_available_listings_get_an_opening_record_and_upcoming_listings_are_flagged(): void
    {
        $farmer = $this->farmer();
        $harvestedOn = Carbon::parse('2026-10-01');
        $available = $this->listingFor($farmer, [
            'quantity_available' => 12,
            'harvested_on' => $harvestedOn,
            'price_per_unit' => 30,
            'stock_tracked_since' => null,
            'needs_actual_harvest' => false,
        ]);
        $upcoming = $this->listingFor($farmer, [
            'quantity_available' => 9,
            'available_from' => now()->addDays(3),
            'stock_tracked_since' => null,
        ]);
        $empty = $this->listingFor($farmer, [
            'quantity_available' => 0,
            'stock_tracked_since' => null,
        ]);
        $deleted = $this->listingFor($farmer, ['quantity_available' => 4]);
        $deleted->delete();

        app(BackfillOpeningHarvestRecords::class)->handle();

        $available->refresh();
        $record = HarvestRecord::query()->where('listing_id', $available->id)->first();
        $this->assertNotNull($record);
        $this->assertSame(HarvestRecordKind::Opening, $record->kind);
        $this->assertSame('12.00', HarvestInput::scale($record->quantity_harvested));
        $this->assertSame('12.00', HarvestInput::scale($record->quantity_good));
        $this->assertSame('0.00', HarvestInput::scale($record->quantity_rejected));
        $this->assertNull($record->production_cost);
        $this->assertNull($record->recorded_by);
        $this->assertSame('2026-10-01', $record->harvested_on?->toDateString());
        $this->assertNotNull($available->stock_tracked_since);
        $this->assertFalse($available->needs_actual_harvest);

        $upcoming->refresh();
        $this->assertTrue($upcoming->needs_actual_harvest);
        $this->assertNotNull($upcoming->stock_tracked_since);
        $this->assertSame(0, HarvestRecord::query()->where('listing_id', $upcoming->id)->count());

        $empty->refresh();
        $this->assertNotNull($empty->stock_tracked_since);
        $this->assertSame(0, HarvestRecord::query()->where('listing_id', $empty->id)->count());
        $this->assertSame(0, HarvestRecord::query()->where('listing_id', $deleted->id)->count());
    }

    public function test_available_now_create_records_the_harvest_and_rejects_a_total_loss(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['floor_price' => 20, 'max_discount' => 5]);

        $created = $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => 'Morning pechay',
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'harvested_on' => '2026-10-08',
            'quantity_harvested' => 10,
            'quantity_rejected' => 2,
            'rejection_reason' => 'spoiled',
            'production_cost' => 40,
        ])->assertCreated();

        $listing = Listing::query()->findOrFail($created->json('data.id'));
        $this->assertSame(8.0, (float) $listing->quantity_available);
        $this->assertSame('2026-10-08', $listing->harvested_on?->toDateString());
        $this->assertFalse($listing->needs_actual_harvest);
        $this->assertNotNull($listing->stock_tracked_since);

        $record = $listing->harvestRecords()->first();
        $this->assertSame(HarvestRecordKind::Initial, $record->kind);
        $this->assertSame('8.00', HarvestInput::scale($record->quantity_good));
        $this->assertSame('40.00', HarvestInput::scale($record->production_cost));
        $this->assertSame('30.0000', number_format((float) $record->price_per_unit, 4, '.', ''));

        $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => 'Nothing left',
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 5,
            'quantity_rejected' => 5,
            'rejection_reason' => 'pests',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.quantity_harvested.0', 'Nothing left to sell after rejects');
    }

    public function test_an_old_create_payload_still_opens_an_initial_record(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['floor_price' => 20, 'max_discount' => 5]);

        $created = $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => 'Fresh kamatis, hand picked',
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'quantity_available' => 20,
        ])->assertCreated();

        $listing = Listing::query()->findOrFail($created->json('data.id'));
        $record = $listing->harvestRecords()->first();
        $this->assertSame(HarvestRecordKind::Initial, $record->kind);
        $this->assertSame('20.00', HarvestInput::scale($record->quantity_harvested));
        $this->assertSame('0.00', HarvestInput::scale($record->quantity_rejected));
        $this->assertNull($record->production_cost);
        $this->assertSame(20.0, (float) $listing->quantity_available);
    }

    public function test_an_upcoming_listing_stores_the_expected_quantity_without_a_record(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['floor_price' => 20, 'max_discount' => 5]);

        $created = $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => 'Next week okra',
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'quantity_available' => 12,
            'available_from' => now()->addDays(4)->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.needs_actual_harvest', true);

        $listing = Listing::query()->findOrFail($created->json('data.id'));
        $this->assertSame(12.0, (float) $listing->quantity_available);
        $this->assertSame(0, $listing->harvestRecords()->count());
    }

    public function test_edit_ignores_an_unchanged_quantity_and_rejects_a_real_change(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['floor_price' => 20, 'max_discount' => 5]);
        $listingId = $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => 'Sitaw',
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'quantity_available' => 50,
        ])->assertCreated()->json('data.id');

        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listingId}", [
            'quantity_available' => '50',
            'title' => 'Sitaw, sariwa',
        ])->assertOk()->assertJsonPath('data.title', 'Sitaw, sariwa');

        $this->assertSame(50.0, (float) Listing::query()->find($listingId)->quantity_available);

        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listingId}", [
            'quantity_available' => 40,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.quantity_available.0', 'Use Add stock or Remove stock to change the quantity.');
    }

    public function test_clearing_available_from_records_an_estimate_and_crop_locks_after_a_real_record(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['floor_price' => 0, 'max_discount' => 0, 'name' => 'Pechay']);
        $other = $this->cropType(['floor_price' => 0, 'max_discount' => 0, 'name' => 'Okra']);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $crop->id,
            'quantity_available' => 10,
            'price_per_unit' => 30,
            'available_from' => now()->addDays(2),
            'needs_actual_harvest' => true,
        ]);

        app(BackfillOpeningHarvestRecords::class)->handle();
        $this->assertSame(0, $listing->harvestRecords()->count());

        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}", [
            'crop_type_id' => $other->id,
        ])->assertOk();
        $this->assertSame($other->id, $listing->fresh()->crop_type_id);

        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}", [
            'available_from' => null,
        ])->assertOk()->assertJsonPath('data.needs_actual_harvest', false);

        $estimate = $listing->harvestRecords()->first();
        $this->assertSame(HarvestRecordKind::Estimated, $estimate->kind);
        $this->assertSame('10.00', HarvestInput::scale($estimate->quantity_good));
        $this->assertNull($estimate->production_cost);

        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}", [
            'crop_type_id' => $crop->id,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.crop_type_id.0', 'Create a new listing for a different crop or unit.');
    }

    public function test_add_stock_snapshots_price_and_accepts_a_cost_breakdown(): void
    {
        $farmer = $this->farmer();
        $listing = $this->tracked($farmer, 20, 30);
        $listing->forceFill(['harvested_on' => '2026-10-01'])->save();

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/harvests", [
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 6,
            'quantity_rejected' => 1,
            'rejection_reason' => 'bruised_damaged',
            'cost_breakdown' => ['seeds' => 150, 'labor' => 50],
        ])->assertOk();

        $listing->refresh();
        $this->assertSame(25.0, (float) $listing->quantity_available);
        $this->assertSame(now()->toDateString(), $listing->harvested_on?->toDateString());

        $added = $listing->harvestRecords()->where('kind', HarvestRecordKind::Added)->first();
        $this->assertSame('5.00', HarvestInput::scale($added->quantity_good));
        $this->assertSame('200.00', HarvestInput::scale($added->production_cost));
        $this->assertSame('30.0000', number_format((float) $added->price_per_unit, 4, '.', ''));
        $this->assertSame(150.0, (float) $added->cost_breakdown['seeds']);

        $this->asUser($farmer)->post("/api/farmer/listings/{$listing->id}/harvests", [
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 2,
            'cost_breakdown' => ['seeds' => '80', 'packaging' => ''],
        ], ['Accept' => 'application/json'])->assertOk();

        $multipart = $listing->harvestRecords()->where('kind', HarvestRecordKind::Added)->latest('id')->first();
        $this->assertSame('80.00', HarvestInput::scale($multipart->production_cost));
        $this->assertArrayNotHasKey('packaging', $multipart->cost_breakdown ?? []);

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/harvests", [
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 2,
            'production_cost' => 10,
            'cost_breakdown' => ['seeds' => 150],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('production_cost');
    }

    public function test_value_added_reasons_are_enforced_and_add_stock_is_refused_when_it_cannot_be_sold(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType([
            'category' => ProductCategory::ValueAdded,
            'floor_price' => 0,
            'max_discount' => 0,
        ]);
        $listing = $this->tracked($farmer, 8, 40, ['crop_type_id' => $crop->id]);

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/harvests", $this->harvestBody([
            'rejection_reason' => 'pests',
            'quantity_rejected' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/harvests", $this->harvestBody([
            'quantity_harvested' => 3,
            'quantity_rejected' => 1,
            'rejection_reason' => 'defective',
        ]))->assertOk();

        $waiting = $this->tracked($farmer, 10, 30, ['needs_actual_harvest' => true]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$waiting->id}/harvests", $this->harvestBody())
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', 'Record the actual harvest first');

        $expired = $this->tracked($farmer, 10, 30, ['available_until' => now()->subHour()]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$expired->id}/harvests", $this->harvestBody())
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', 'Extend the listing first');

        $takenDown = $this->tracked($farmer, 10, 30, ['status' => ListingStatus::TakenDown]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$takenDown->id}/harvests", $this->harvestBody())
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', 'This listing has been taken down.');

        $deleted = $this->tracked($farmer, 10, 30);
        $deleted->delete();
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$deleted->id}/harvests", $this->harvestBody())
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', 'This listing has been deleted.');
    }

    public function test_actual_harvest_replaces_expected_stock_and_a_shortfall_waits_for_confirmation(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'quantity_available' => 10,
            'price_per_unit' => 30,
            'available_from' => now()->addDays(2),
            'needs_actual_harvest' => true,
            'min_order_quantity' => 1,
            'order_step' => 1,
        ]);
        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'listing_id' => $listing->id,
            'quantity' => 8,
            'fulfillment_preference' => 'buyer_pickup',
        ])->assertCreated();

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/actual-harvest", $this->harvestBody([
            'quantity_harvested' => 3,
        ]))->assertUnprocessable()->assertJsonValidationErrors('confirm_cancel_reservations');

        $this->assertSame(10.0, (float) $listing->fresh()->quantity_available);
        $this->assertTrue($listing->fresh()->needs_actual_harvest);

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/actual-harvest", $this->harvestBody([
            'quantity_harvested' => 3,
            'confirm_cancel_reservations' => true,
        ]))->assertOk()->assertJsonPath('data.needs_actual_harvest', false);

        $this->assertSame(3.0, (float) $listing->fresh()->quantity_available);
        $this->assertSame(ReservationStatus::Active, Reservation::query()->first()->status);

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/open")->assertOk();

        $this->assertSame(ReservationStatus::Cancelled, Reservation::query()->first()->status);
        $this->assertSame(ReservationCancellationReason::HarvestShortfall, Reservation::query()->first()->cancellation_reason);
        $this->assertSame(3.0, (float) $listing->fresh()->quantity_available);
    }

    public function test_an_estimate_is_written_before_conversion_by_open_now_and_by_the_command(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $due = $this->listingFor($farmer, [
            'title' => 'Due okra',
            'quantity_available' => 10,
            'price_per_unit' => 30,
            'available_from' => now()->addHour(),
            'needs_actual_harvest' => true,
        ]);
        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'listing_id' => $due->id,
            'quantity' => 4,
            'fulfillment_preference' => 'buyer_pickup',
        ])->assertCreated();

        $this->travelTo($due->available_from->copy()->addMinute());
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$due->id}/open")->assertOk();

        $estimate = $due->harvestRecords()->first();
        $this->assertSame(HarvestRecordKind::Estimated, $estimate->kind);
        $this->assertSame('10.00', HarvestInput::scale($estimate->quantity_good));
        $this->assertSame(ReservationStatus::Converted, Reservation::query()->first()->status);
        $this->assertFalse($due->fresh()->needs_actual_harvest);

        $quiet = $this->listingFor($farmer, [
            'title' => 'No reservations',
            'quantity_available' => 6,
            'price_per_unit' => 30,
            'available_from' => now()->addDay(),
            'needs_actual_harvest' => true,
        ]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$quiet->id}/open")->assertOk();
        $this->assertSame(HarvestRecordKind::Estimated, $quiet->harvestRecords()->first()->kind);
        $this->assertSame(0, Order::query()->where('farmer_seller_id', $farmer->id)->whereHas('items', fn ($query) => $query->where('listing_id', $quiet->id))->count());

        $waiting = $this->listingFor($farmer, [
            'title' => 'Command harvest',
            'quantity_available' => 7,
            'price_per_unit' => 30,
            'available_from' => now()->subHour(),
            'needs_actual_harvest' => true,
        ]);
        $this->artisan('listings:harvest-upkeep')->assertSuccessful();
        $this->assertSame(HarvestRecordKind::Estimated, $waiting->harvestRecords()->first()->kind);
        $this->assertFalse($waiting->fresh()->needs_actual_harvest);
    }

    public function test_remove_stock_stays_within_sellable_quantity_and_sends_the_low_stock_notice(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->tracked($farmer, 10, 30);
        $this->placeOrder($buyer, $listing, 3);

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/stock-removals", [
            'quantity' => 8,
            'reason' => 'spoiled',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertStringContainsString('3.00', $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/stock-removals", [
            'quantity' => 8,
            'reason' => 'spoiled',
        ])->json('errors.quantity.0'));

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/stock-removals", [
            'quantity' => 1,
            'reason' => 'correction',
        ])->assertUnprocessable()->assertJsonValidationErrors('note');

        $low = $this->tracked($farmer, 6, 30);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$low->id}/stock-removals", [
            'quantity' => 2,
            'reason' => 'damaged',
            'note' => 'Crushed in the crate',
        ])->assertOk();

        $this->assertSame(4.0, (float) $low->fresh()->quantity_available);
        $this->assertSame(1, InAppNotification::query()->where('user_id', $farmer->id)->where('type', NotificationType::ListingLowStock)->count());

        $waiting = $this->tracked($farmer, 5, 30, ['needs_actual_harvest' => true]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$waiting->id}/stock-removals", [
            'quantity' => 1,
            'reason' => 'spoiled',
        ])->assertUnprocessable()->assertJsonPath('errors.listing.0', 'Record the actual harvest first');

        $expired = $this->tracked($farmer, 5, 30, ['available_until' => now()->subHour()]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$expired->id}/stock-removals", [
            'quantity' => 1,
            'reason' => 'spoiled',
        ])->assertOk();
    }

    public function test_stock_history_identity_counts_confirmed_sales_after_tracking_started(): void
    {
        $farmer = $this->farmer();
        $buyerA = $this->buyer();
        $buyerB = $this->buyer();
        $listing = $this->tracked($farmer, 20, 30);
        $listing->forceFill(['stock_tracked_since' => now()->subDay()])->save();
        HarvestRecord::query()->create([
            'listing_id' => $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $listing->farm_id,
            'crop_type_id' => $listing->crop_type_id,
            'unit' => 'kg',
            'is_value_added' => false,
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 20,
            'quantity_rejected' => 0,
            'quantity_good' => 20,
            'price_per_unit' => 30,
            'kind' => HarvestRecordKind::Opening,
            'recorded_by' => null,
        ]);

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/harvests", $this->harvestBody([
            'quantity_harvested' => 10,
        ]))->assertOk();

        $placed = $this->placeOrder($buyerA, $listing, 4);
        $confirmed = $this->placeOrder($buyerB, $listing, 3);
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$confirmed->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$confirmed->id}/cancel", [
            'reason' => CancellationReason::SellerDeclined->value,
        ])->assertOk();

        $this->asUser($farmer)->postJson('/api/farmer/walk-in-sales', [
            'listing_id' => $listing->id,
            'quantity' => 5,
            'amount_received' => 150,
        ])->assertCreated();

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$placed->id}/confirm")->assertOk();
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/stock-removals", [
            'quantity' => 2,
            'reason' => 'spoiled',
        ])->assertOk();

        $summary = $this->asUser($farmer)->getJson("/api/farmer/listings/{$listing->id}/stock-history")
            ->assertOk()
            ->json('summary');

        $this->assertSame('30.00', $summary['good']);
        $this->assertSame('9.00', $summary['sold']);
        $this->assertSame('19.00', $summary['available']);
        $this->assertSame('2.00', $summary['removed']);
        $this->assertSame('0.00', $summary['held']);
        $this->assertSame(0, bccomp(
            $summary['good'],
            bcadd(bcadd($summary['sold'], $summary['available'], 2), $summary['removed'], 2),
            2,
        ));
        $this->assertSame(OrderStatus::Cancelled, $confirmed->fresh()->status);
    }

    public function test_an_older_sale_does_not_count_against_an_opening_balance(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->tracked($farmer, 20, 30);
        $listing->forceFill(['stock_tracked_since' => now()])->save();
        HarvestRecord::query()->create([
            'listing_id' => $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $listing->farm_id,
            'crop_type_id' => $listing->crop_type_id,
            'unit' => 'kg',
            'is_value_added' => false,
            'harvested_on' => now()->subDays(10)->toDateString(),
            'quantity_harvested' => 20,
            'quantity_rejected' => 0,
            'quantity_good' => 20,
            'price_per_unit' => 30,
            'kind' => HarvestRecordKind::Opening,
        ]);

        $order = Order::query()->create([
            'order_number' => 'OLD-1',
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $listing->farm_id,
            'status' => OrderStatus::Completed,
            'fulfillment_preference' => 'buyer_pickup',
            'subtotal' => 180,
            'tawad_total' => 0,
            'total' => 180,
            'confirmed_at' => now()->subDays(3),
            'completed_at' => now()->subDays(3),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'listing_id' => $listing->id,
            'crop_type_id' => $listing->crop_type_id,
            'listing_name' => $listing->title,
            'unit' => 'kg',
            'quantity' => 6,
            'unit_price' => 30,
            'line_subtotal' => 180,
            'line_total' => 180,
        ]);

        $summary = $this->asUser($farmer)->getJson("/api/farmer/listings/{$listing->id}/stock-history")->json('summary');
        $this->assertSame('0.00', $summary['sold']);
        $this->assertSame('20.00', $summary['good']);
        $this->assertSame('20.00', $summary['available']);
    }

    public function test_buyers_never_see_harvest_details_and_another_seller_cannot_write_them(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $intruder = $this->farmer(['email' => 'intruder@example.com']);
        $buyer = $this->buyer();
        $listing = $this->tracked($owner, 10, 30);
        $this->asUser($owner)->postJson("/api/farmer/listings/{$listing->id}/harvests", $this->harvestBody([
            'production_cost' => 12.5,
        ]))->assertOk();

        $market = $this->asUser($buyer)->getJson("/api/buyer/marketplace/{$listing->id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('needs_actual_harvest', $market);
        $this->assertArrayNotHasKey('expired_with_stock', $market);
        $this->assertArrayNotHasKey('has_harvest_records', $market);
        $this->assertStringNotContainsString('production_cost', json_encode($market));

        $own = $this->asUser($owner)->getJson("/api/farmer/listings/{$listing->id}")->assertOk()->json('data');
        $this->assertArrayHasKey('needs_actual_harvest', $own);
        $this->assertTrue($own['has_harvest_records']);

        $this->asUser($intruder)->getJson("/api/farmer/listings/{$listing->id}/stock-history")->assertForbidden();
        $this->asUser($intruder)->postJson("/api/farmer/listings/{$listing->id}/harvests", $this->harvestBody())->assertForbidden();
        $this->asUser($buyer)->getJson("/api/farmer/listings/{$listing->id}/stock-history")->assertForbidden();
    }

    public function test_harvest_reminder_and_expired_stock_notice_are_sent_once(): void
    {
        $farmer = $this->farmer();
        $soon = $this->listingFor($farmer, [
            'title' => 'Tomorrow talong',
            'quantity_available' => 4,
            'available_from' => now()->addHours(12),
            'needs_actual_harvest' => true,
        ]);
        $ended = $this->listingFor($farmer, [
            'title' => 'Ended pechay',
            'quantity_available' => 3,
            'unit' => 'kg',
            'available_until' => now()->subHour(),
            'needs_actual_harvest' => false,
        ]);

        $this->artisan('listings:harvest-upkeep')->assertSuccessful();
        $this->artisan('listings:harvest-upkeep')->assertSuccessful();

        $this->assertSame(1, InAppNotification::query()->where('type', NotificationType::HarvestReminder)->count());
        $this->assertSame(1, InAppNotification::query()->where('type', NotificationType::ExpiredStockLeft)->count());
        $this->assertNotNull($soon->fresh()->harvest_reminded_at);
        $this->assertNotNull($ended->fresh()->expired_stock_notified_at);

        $reminder = InAppNotification::query()->where('type', NotificationType::HarvestReminder)->first();
        $this->assertStringContainsString('Tomorrow talong', $reminder->body);
        $this->assertSame($soon->id, $reminder->related_id);
        $this->assertStringContainsString('Ended pechay', InAppNotification::query()->where('type', NotificationType::ExpiredStockLeft)->value('body'));
    }

    public function test_export_includes_the_sellers_harvest_records_and_costs(): void
    {
        $farmer = $this->farmer();
        $listing = $this->tracked($farmer, 10, 30);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/harvests", $this->harvestBody([
            'production_cost' => 12.5,
        ]))->assertOk();
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/stock-removals", [
            'quantity' => 1,
            'reason' => 'spoiled',
        ])->assertOk();

        $export = app(ExportOwnDataAction::class)->handle($farmer);
        $this->assertSame('12.50', HarvestInput::scale($export['harvest_records'][0]['production_cost']));
        $this->assertSame('spoiled', $export['stock_removals'][0]['reason']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function tracked(User $farmer, float $quantity, float $price, array $extra = []): Listing
    {
        return $this->listingFor($farmer, [
            'quantity_available' => $quantity,
            'price_per_unit' => $price,
            'stock_tracked_since' => now()->subDay(),
            'needs_actual_harvest' => false,
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function harvestBody(array $overrides = []): array
    {
        return [
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 4,
            'quantity_rejected' => 0,
            ...$overrides,
        ];
    }
}
