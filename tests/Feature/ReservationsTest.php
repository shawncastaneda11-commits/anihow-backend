<?php

namespace Tests\Feature;

use App\Actions\Listings\TakeDownListingAction;
use App\Actions\Privacy\AnonymizeUserAction;
use App\Actions\Privacy\ExportOwnDataAction;
use App\Actions\Reservations\ReserveListing;
use App\Enums\FulfillmentPreference;
use App\Enums\NotificationType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\CartItem;
use App\Models\Farm;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ReservationsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 08:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_buyer_reserves_an_upcoming_harvest_at_the_locked_price(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, price: 30, quantity: 10);
        $buyer = $this->buyer();

        $response = $this->reserve($buyer, $listing, 2.5, 'seller_delivers', 'Morning gate')
            ->assertCreated()
            ->assertJsonPath('data.unit_price', 30)
            ->assertJsonPath('data.line_total', 75)
            ->assertJsonPath('data.fulfillment_preference', 'seller_delivers')
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(0.0, (float) $listing->fresh()->quantity_held);
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::ReservationMade));
        $this->assertSame($response->json('data.id'), Reservation::query()->value('id'));
    }

    public function test_reserving_again_updates_the_same_row_and_relocks_the_price(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer);
        $buyer = $this->buyer();

        $first = $this->reserve($buyer, $listing, 2)->assertCreated()->json('data.id');

        $listing->update(['price_per_unit' => 40]);

        $this->reserve($buyer, $listing, 3)
            ->assertOk()
            ->assertJsonPath('data.id', $first)
            ->assertJsonPath('data.unit_price', 40)
            ->assertJsonPath('data.quantity', 3);

        $this->assertSame(1, Reservation::query()->count());
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::ReservationMade));
    }

    public function test_a_decimal_quantity_is_accepted_on_the_same_rules_as_the_cart(): void
    {
        $listing = $this->upcoming($this->farmer(), quantity: 10);

        $this->reserve($this->buyer(), $listing, 1.5)->assertCreated();

        $this->reserve($this->buyer(), $this->upcoming($this->farmer()), 0)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_the_fourth_active_reservation_is_rejected_and_an_update_does_not_use_a_slot(): void
    {
        $buyer = $this->buyer();
        $farmer = $this->farmer();
        $listings = collect(range(1, 4))->map(fn () => $this->upcoming($farmer));

        foreach ($listings->take(3) as $listing) {
            $this->reserve($buyer, $listing, 1)->assertCreated();
        }

        $this->reserve($buyer, $listings->first(), 2)->assertOk();

        $this->reserve($buyer, $listings->last(), 1)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');

        $this->assertSame(3, Reservation::query()->where('status', ReservationStatus::Active)->count());
    }

    public function test_over_quantity_is_rejected_against_other_active_reservations(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, quantity: 10);

        $this->reserve($this->buyer(), $listing, 8)->assertCreated();

        $this->reserve($this->buyer(), $listing, 3)
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'Only 2.00 available.');
    }

    public function test_a_listing_that_is_not_upcoming_cannot_be_reserved(): void
    {
        $listing = $this->listingFor($this->farmer(), ['quantity_available' => 10]);

        $this->reserve($this->buyer(), $listing, 1)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');
    }

    public function test_a_hidden_listing_is_not_found(): void
    {
        $listing = $this->upcoming($this->farmer());
        $listing->update(['is_active' => false]);

        $this->reserve($this->buyer(), $listing, 1)->assertNotFound();
    }

    public function test_conversion_keeps_the_locked_price_when_the_listing_price_changes(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, price: 30);
        $buyer = $this->buyer();
        $this->reserve($buyer, $listing, 2)->assertCreated();

        $listing->update(['price_per_unit' => 90]);
        $this->open($farmer, $listing);

        $item = Order::query()->firstOrFail()->items()->first();
        $this->assertSame(30.0, (float) $item->unit_price);
        $this->assertSame(OrderSource::App, Order::query()->first()->source);
        $this->assertSame(OrderStatus::Placed, Order::query()->first()->status);
    }

    public function test_conversion_keeps_the_locked_price_when_the_floor_is_raised(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, price: 30);
        $this->reserve($this->buyer(), $listing, 2)->assertCreated();

        $listing->cropType->update(['floor_price' => 100]);

        $this->open($farmer, $listing);

        $this->assertSame(30.0, (float) Order::query()->firstOrFail()->items()->first()->unit_price);
    }

    public function test_fifo_conversion_cancels_the_remainder_as_a_harvest_shortfall(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, quantity: 10);
        $first = $this->buyer();
        $second = $this->buyer();

        $this->reserve($first, $listing, 6)->assertCreated();
        $this->reserve($second, $listing, 4)->assertCreated();

        $listing->update(['quantity_available' => 5]);
        $this->travelToOpen($listing);
        $this->artisan('reservations:open-due')->assertSuccessful();

        $kept = Reservation::query()->where('buyer_id', $first->id)->first();
        $dropped = Reservation::query()->where('buyer_id', $second->id)->first();

        $this->assertSame(ReservationStatus::Cancelled, $kept->status);
        $this->assertSame(ReservationCancellationReason::HarvestShortfall, $kept->cancellation_reason);
        $this->assertSame(ReservationStatus::Converted, $dropped->status);
        $this->assertSame(4.0, (float) $listing->fresh()->quantity_held);
        $this->assertSame(1, $this->notices($first->id, NotificationType::ReservationCancelled));
        $this->assertSame(1, $this->notices($second->id, NotificationType::ReservationConverted));
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::OrderPlaced));
    }

    public function test_order_timers_start_at_conversion_not_at_reservation(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, from: now()->addHours(47), until: now()->addDays(10));
        $buyer = $this->buyer();
        $this->reserve($buyer, $listing, 2)->assertCreated();
        $reservedAt = now()->copy();

        $this->travelToOpen($listing);
        $this->artisan('reservations:open-due')->assertSuccessful();

        $order = Order::query()->firstOrFail();
        $this->assertTrue($order->created_at->equalTo(now()));
        $this->assertTrue($order->created_at->greaterThan($reservedAt));

        Carbon::setTestNow($reservedAt->copy()->addHours(48));
        $this->artisan('orders:sweep-stale')->assertSuccessful();
        $this->assertSame(OrderStatus::Placed, $order->fresh()->status);

        Carbon::setTestNow($order->created_at->copy()->addHours(12));
        $this->artisan('orders:sweep-stale')->assertSuccessful();
        $this->assertNotNull($order->fresh()->reminder_sent_at);
        $this->assertSame(OrderStatus::Placed, $order->fresh()->status);

        Carbon::setTestNow($order->created_at->copy()->addHours(48));
        $this->artisan('orders:sweep-stale')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_opening_due_reservations_is_idempotent(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer);
        $this->reserve($this->buyer(), $listing, 2)->assertCreated();
        $this->travelToOpen($listing);

        $this->artisan('reservations:open-due')->assertSuccessful();
        $this->artisan('reservations:open-due')->assertSuccessful();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2.0, (float) $listing->fresh()->quantity_held);
        $this->assertNull(Reservation::query()->first()->active_slot);
    }

    public function test_a_cart_add_converts_due_reservations_before_it_takes_stock(): void
    {
        [$farmer, $listing, $buyer] = $this->reservedAll();
        $this->travelToOpen($listing);

        $this->asUser($this->buyer())->postJson('/api/buyer/cart', [
            'listing_id' => $listing->id,
            'quantity' => 10,
        ])->assertUnprocessable();

        $this->assertSame(ReservationStatus::Converted, Reservation::query()->first()->status);
        $this->assertSame(10.0, (float) $listing->fresh()->quantity_held);
        $this->assertSame(0, CartItem::query()->count());
        $this->assertNotNull(Reservation::query()->first()->order_id);
        $this->assertSame($buyer->id, Order::query()->first()->buyer_id);
        $this->assertNotNull($farmer->id);
    }

    public function test_checkout_converts_due_reservations_before_the_cart_takes_stock(): void
    {
        [$farmer, $listing] = $this->reservedAll();
        $this->travelToOpen($listing);

        $shopper = $this->buyer();
        CartItem::query()->create([
            'buyer_id' => $shopper->id,
            'listing_id' => $listing->id,
            'quantity' => 10,
        ]);

        $this->checkout($shopper)->assertUnprocessable();

        $this->assertSame(ReservationStatus::Converted, Reservation::query()->first()->status);
        $this->assertSame(10.0, (float) $listing->fresh()->quantity_held);
        $this->assertSame(1, Order::query()->count());
        $this->assertNotSame($shopper->id, Order::query()->first()->buyer_id);
        $this->assertNotNull($farmer->id);
    }

    public function test_a_walk_in_converts_due_reservations_before_it_takes_stock(): void
    {
        [$farmer, $listing] = $this->reservedAll();
        $this->travelToOpen($listing);

        $this->asUser($farmer)->postJson('/api/farmer/walk-in-sales', [
            'listing_id' => $listing->id,
            'quantity' => 10,
            'amount_received' => 300,
        ])->assertUnprocessable();

        $listing->refresh();
        $this->assertSame(ReservationStatus::Converted, Reservation::query()->first()->status);
        $this->assertSame(10.0, (float) $listing->quantity_held);
        $this->assertSame(10.0, (float) $listing->quantity_available);
        $this->assertSame(OrderSource::App, Order::query()->first()->source);
    }

    public function test_a_listing_that_expires_before_opening_cancels_reservations(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, from: now()->addDays(10), until: now()->addDays(3));
        $buyer = $this->buyer();
        $this->reserve($buyer, $listing, 2)->assertCreated();

        Carbon::setTestNow(now()->addDays(3));
        $this->artisan('reservations:open-due')->assertSuccessful();

        $reservation = Reservation::query()->first();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(ReservationCancellationReason::ListingRemoved, $reservation->cancellation_reason);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::ReservationCancelled));
        $this->assertNotNull($farmer->id);
    }

    public function test_takedown_deactivate_and_delete_cancel_active_reservations(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $buyer = $this->buyer();

        $takenDown = $this->upcoming($farmer);
        $this->reserve($buyer, $takenDown, 1)->assertCreated();
        app(TakeDownListingAction::class)->handle($takenDown, $admin, 'Not this harvest');

        $paused = $this->upcoming($farmer);
        $this->reserve($buyer, $paused, 1)->assertCreated();
        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$paused->id}/active", [
            'is_active' => false,
        ])->assertOk();

        $removed = $this->upcoming($farmer);
        $this->reserve($buyer, $removed, 1)->assertCreated();
        $this->asUser($farmer)->deleteJson("/api/farmer/listings/{$removed->id}")->assertOk();

        $this->assertSame(3, Reservation::query()->where('status', ReservationStatus::Cancelled)->count());
        $this->assertSame(
            3,
            Reservation::query()->where('cancellation_reason', ReservationCancellationReason::ListingRemoved)->count(),
        );
        $this->assertSame(3, $this->notices($buyer->id, NotificationType::ReservationCancelled));
        $this->assertSoftDeleted($removed);
    }

    public function test_suspending_a_seller_cancels_active_reservations(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->upcoming($farmer);
        app(ReserveListing::class)->handle(
            $buyer,
            $listing->id,
            1,
            FulfillmentPreference::BuyerPickup,
            null,
        );

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('suspend', $farmer, data: [
                'suspension_reason' => 'Stopped selling',
            ])
            ->assertHasNoTableActionErrors();

        $reservation = Reservation::query()->first();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(ReservationCancellationReason::ListingRemoved, $reservation->cancellation_reason);
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::ReservationCancelled));
    }

    public function test_the_buyer_and_the_seller_can_cancel_and_nobody_else_can(): void
    {
        $farmer = $this->farmer();
        $otherFarmer = $this->farmer();
        $listing = $this->upcoming($farmer);
        $buyer = $this->buyer();
        $other = $this->buyer();
        $id = $this->reserve($buyer, $listing, 1)->assertCreated()->json('data.id');

        $this->asUser($other)->patchJson("/api/buyer/reservations/{$id}")->assertForbidden();
        $this->asUser($otherFarmer)->getJson("/api/farmer/listings/{$listing->id}/reservations")->assertForbidden();

        $this->asUser($buyer)->getJson('/api/buyer/reservations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);
        $this->asUser($other)->getJson('/api/buyer/reservations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($farmer)->getJson("/api/farmer/listings/{$listing->id}/reservations")
            ->assertOk()
            ->assertJsonPath('data.0.buyer.name', $buyer->name);

        $this->asUser($buyer)->patchJson("/api/buyer/reservations/{$id}")->assertOk();
        $this->assertSame(ReservationCancellationReason::BuyerCancelled, Reservation::query()->first()->cancellation_reason);
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ReservationCancelled));

        $again = $this->reserve($buyer, $listing, 1)->assertCreated()->json('data.id');
        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}/reservations/{$again}", [
            'note' => 'Crop failed',
        ])->assertOk();

        $this->assertSame(ReservationCancellationReason::SellerCancelled, Reservation::query()->find($again)->cancellation_reason);
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::ReservationCancelled));
    }

    public function test_a_content_editor_cannot_open_reservations(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());
        $listing = $this->upcoming($this->farmer());

        $this->actingAs($admin)->get('/admin/reservations')->assertOk();

        $this->actingAs($editor);
        $this->assertFalse(ReservationResource::canAccess());
        $this->get('/admin/reservations')->assertForbidden();

        $this->asUser($editor)
            ->getJson("/api/farmer/listings/{$listing->id}/reservations")
            ->assertForbidden();
    }

    public function test_farmer_listing_json_includes_reserved_totals_and_the_marketplace_does_not(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, quantity: 10);
        $this->reserve($this->buyer(), $listing, 4)->assertCreated();

        $farmerPayload = $this->asUser($farmer)
            ->getJson("/api/farmer/listings/{$listing->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame(4.0, (float) $farmerPayload['reserved_quantity']);
        $this->assertSame(1, $farmerPayload['active_reservations_count']);

        $market = $this->asUser($this->buyer())
            ->getJson("/api/buyer/marketplace/{$listing->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('reserved_quantity', $market);
        $this->assertArrayNotHasKey('active_reservations_count', $market);
    }

    public function test_lowering_quantity_below_the_reserved_amount_warns_without_blocking_the_save(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, quantity: 10);
        $this->reserve($this->buyer(), $listing, 6)->assertCreated();

        $quiet = $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}", [
            'description' => 'Still the same harvest',
        ])->assertOk();

        $this->assertArrayNotHasKey('warning', $quiet->json());

        $warned = $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}", [
            'quantity_available' => 2,
        ])->assertOk();

        $this->assertSame(2.0, (float) $listing->fresh()->quantity_available);
        $this->assertNotEmpty($warned->json('warning'));
    }

    public function test_a_converted_order_records_that_it_came_from_a_reservation(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer);
        $buyer = $this->buyer();
        $this->reserve($buyer, $listing, 1)->assertCreated();
        $this->open($farmer, $listing);

        $this->asUser($buyer)->getJson('/api/buyer/orders/'.Order::query()->value('id'))
            ->assertOk()
            ->assertJsonPath('data.from_reservation', true)
            ->assertJsonPath('data.reservation_id', Reservation::query()->value('id'));
    }

    public function test_export_includes_reservations_and_anonymizing_cancels_them(): void
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer);
        $buyer = $this->buyer();
        $this->reserve($buyer, $listing, 2)->assertCreated();

        $export = app(ExportOwnDataAction::class)->handle($buyer);
        $this->assertSame($listing->title, $export['reservations'][0]['listing_name']);
        $this->assertSame('active', $export['reservations'][0]['status']);

        app(AnonymizeUserAction::class)->handle($buyer);

        $buyerReservation = Reservation::query()->first();
        $this->assertSame(ReservationStatus::Cancelled, $buyerReservation->status);
        $this->assertSame(ReservationCancellationReason::AccountClosed, $buyerReservation->cancellation_reason);
        $this->assertSame(0, $this->notices($farmer->id, NotificationType::ReservationCancelled));

        $nextBuyer = $this->buyer();
        $this->reserve($nextBuyer, $listing, 1)->assertCreated();

        app(AnonymizeUserAction::class)->handle($farmer->fresh());

        $sellerReservation = Reservation::query()->where('buyer_id', $nextBuyer->id)->first();
        $this->assertSame(ReservationCancellationReason::AccountClosed, $sellerReservation->cancellation_reason);
        $this->assertSame(1, $this->notices($nextBuyer->id, NotificationType::ReservationCancelled));
    }

    private function upcoming(
        User $farmer,
        float $price = 30,
        float $quantity = 10,
        ?Carbon $from = null,
        ?Carbon $until = null,
    ): Listing {
        return $this->listingFor($farmer, [
            'price_per_unit' => $price,
            'quantity_available' => $quantity,
            'available_from' => $from ?? now()->addDays(3),
            'available_until' => $until ?? now()->addDays(10),
        ]);
    }

    private function reserve(
        User $buyer,
        Listing $listing,
        float $quantity,
        string $preference = 'buyer_pickup',
        ?string $note = null,
    ): TestResponse {
        return $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'listing_id' => $listing->id,
            'quantity' => $quantity,
            'fulfillment_preference' => $preference,
            'fulfillment_note' => $note,
        ]);
    }

    private function open(User $farmer, Listing $listing): void
    {
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/open")->assertOk();
    }

    private function travelToOpen(Listing $listing): void
    {
        Carbon::setTestNow($listing->available_from->copy());
    }

    /**
     * @return array{0: User, 1: Listing, 2: User}
     */
    private function reservedAll(): array
    {
        $farmer = $this->farmer();
        $listing = $this->upcoming($farmer, quantity: 10);
        $buyer = $this->buyer();
        $this->reserve($buyer, $listing, 10)->assertCreated();

        return [$farmer, $listing, $buyer];
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function notices(int $userId, NotificationType $type): int
    {
        return InAppNotification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->count();
    }
}
