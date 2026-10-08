<?php

namespace Tests\Feature;

use App\Actions\Pricing\FlagStrandedListingsAction;
use App\Enums\ListingUnit;
use App\Enums\OrderSource;
use App\Enums\ProductCategory;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\TawadRule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmFeatureSwitchTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_defaults_are_on_and_login_includes_farm_features(): void
    {
        $farm = Farm::factory()->create();

        $this->assertTrue($farm->allowsValueAdded());
        $this->assertTrue($farm->allowsReservations());
        $this->assertTrue($farm->allowsTawad());
        $this->assertTrue($farm->allowsWalkIn());

        DB::table('farms')->insert([
            'name' => 'Legacy Farm',
            'slug' => 'legacy-farm',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacy = Farm::query()->where('slug', 'legacy-farm')->firstOrFail();

        $this->assertTrue($legacy->value_added_enabled);
        $this->assertTrue($legacy->reservations_enabled);
        $this->assertTrue($legacy->tawad_enabled);
        $this->assertTrue($legacy->walk_in_enabled);

        $farmer = $this->farmer([], $farm);

        $this->postJson('/api/auth/login', [
            'email' => $farmer->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.farm.features.value_added', true)
            ->assertJsonPath('data.farm.features.reservations', true)
            ->assertJsonPath('data.farm.features.tawad', true)
            ->assertJsonPath('data.farm.features.walk_in', true);

        $this->asUser($farmer)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.farm.features.value_added', true)
            ->assertJsonPath('data.farm.features.reservations', true)
            ->assertJsonPath('data.farm.features.tawad', true)
            ->assertJsonPath('data.farm.features.walk_in', true);
    }

    public function test_farm_a_is_blocked_while_farm_b_can_be_bought_and_reserved(): void
    {
        [$farmA, $farmerA, $farmB, $farmerB, $buyer, $jam] = $this->twoFarms();
        $jamA = $this->jam($farmerA, 'Farm A Jam', $jam);
        $jamB = $this->jam($farmerB, 'Farm B Jam', $jam);
        $this->discount($jamB);
        $upcomingA = $this->upcoming($farmerA, 'Farm A Harvest');
        $this->discount($upcomingA);
        $upcomingB = $this->upcoming($farmerB, 'Farm B Harvest');
        $this->discount($upcomingB);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Farm A Jam'])
            ->assertJsonFragment(['title' => 'Farm B Jam'])
            ->assertJsonFragment(['title' => 'Farm B Harvest']);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$jamA->id)
            ->assertNotFound();

        $this->asUser($buyer)
            ->getJson('/api/buyer/shops/'.$farmerA->id)
            ->assertOk()
            ->assertJsonMissing(['title' => 'Farm A Jam'])
            ->assertJsonPath('data.farm.features.value_added', false);

        $this->asUser($buyer)
            ->getJson('/api/buyer/shops/'.$farmerB->id)
            ->assertOk()
            ->assertJsonFragment(['title' => 'Farm B Jam'])
            ->assertJsonPath('data.farm.features.value_added', true)
            ->assertJsonPath('data.farm.features.tawad', true);

        $counts = $this->asUser($buyer)->getJson('/api/crop-types')->assertOk()->json('data');
        $jamCount = collect($counts)->firstWhere('id', $jam->id);

        $this->assertNotNull($jamCount);
        $this->assertSame(1, $jamCount['listings_count']);

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $jamA->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', 'Farm A Jam is no longer available.');

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $upcomingA->id,
                'quantity' => 1,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', Farm::RESERVATIONS_OFF_MESSAGE);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$upcomingA->id)
            ->assertOk()
            ->assertJsonPath('data.can_reserve', false)
            ->assertJsonPath('data.tawad', null);

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $jamB->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.tawad_amount', 5);

        $this->checkout($buyer)->assertCreated();

        $sold = OrderItem::query()->where('listing_id', $jamB->id)->firstOrFail();
        $this->assertEquals(5, (float) $sold->tawad_amount);

        $reserved = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $upcomingB->id,
                'quantity' => 1,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();

        $this->assertEquals(5, (float) Reservation::query()->findOrFail($reserved->json('data.id'))->tawad_amount);

        $this->asUser($farmerA)
            ->postJson('/api/farmer/listings', $this->listingPayload($jam, 'Secret Jam'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.crop_type_id.0', Farm::VALUE_ADDED_OFF_MESSAGE);

        $tomato = $this->produce($farmerA, 'Farm A Tomato');

        $this->asUser($farmerA)
            ->patchJson('/api/farmer/listings/'.$tomato->id, [
                'crop_type_id' => $jam->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.crop_type_id.0', Farm::VALUE_ADDED_OFF_MESSAGE);

        $farmerTypes = $this->asUser($farmerA)->getJson('/api/crop-types')->assertOk()->json('data');
        $this->assertNull(collect($farmerTypes)->firstWhere('id', $jam->id));

        $buyerTypes = $this->asUser($farmerB)->getJson('/api/crop-types')->assertOk()->json('data');
        $this->assertNotNull(collect($buyerTypes)->firstWhere('id', $jam->id));

        $farmA->update(['value_added_enabled' => true]);
        $hidden = $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $jamA->id,
                'quantity' => 1,
            ])
            ->assertCreated();
        $farmA->update(['value_added_enabled' => false]);
        $this->addToCart($buyer, $jamB->fresh(), 1);

        $this->asUser($buyer)
            ->patchJson('/api/buyer/cart/'.$hidden->json('data.id'), ['quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'Farm A Jam is no longer available.');

        $cart = $this->asUser($buyer)->getJson('/api/buyer/cart')->assertOk()->json('data');
        $titles = collect($cart)->pluck('listing.title');

        $this->assertTrue($titles->contains('Farm A Jam'));
        $this->assertTrue($titles->contains('Farm B Jam'));

        $this->checkout($buyer)
            ->assertUnprocessable()
            ->assertJsonPath('errors.cart.0', 'Farm A Jam is no longer available.');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, Order::query()->where('buyer_id', $buyer->id)->whereHas(
            'items',
            fn ($items) => $items->where('listing_id', $jamA->id),
        )->count());
    }

    public function test_an_active_value_added_reservation_still_converts_on_harvest_day(): void
    {
        $farm = Farm::factory()->create();
        $farmer = $this->farmer([], $farm);
        $buyer = $this->buyer();
        $jam = $this->valueAddedCrop();
        $listing = $this->upcoming($farmer, 'Reserved Jam', $jam);

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $listing->id,
                'quantity' => 2,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();

        $farm->update([
            'value_added_enabled' => false,
            'reservations_enabled' => false,
        ]);

        Carbon::setTestNow($listing->available_from->copy());
        $this->artisan('reservations:open-due')->assertSuccessful();

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame(ReservationStatus::Converted, $reservation->status);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_reservations_off_rejects_new_ones_and_finishes_the_existing_one(): void
    {
        $farm = Farm::factory()->create();
        $farmer = $this->farmer([], $farm);
        $buyer = $this->buyer();
        $other = $this->buyer();
        $listing = $this->upcoming($farmer, 'Morning Harvest');

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $listing->id,
                'quantity' => 2,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();

        $farm->update(['reservations_enabled' => false]);

        $this->asUser($other)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', Farm::RESERVATIONS_OFF_MESSAGE);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.can_reserve', false);

        Carbon::setTestNow($listing->available_from->copy());
        $this->artisan('reservations:open-due')->assertSuccessful();

        $this->assertSame(ReservationStatus::Converted, Reservation::query()->firstOrFail()->status);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_tawad_off_pauses_new_prices_and_keeps_snapshots(): void
    {
        $farm = Farm::factory()->create();
        $farmer = $this->farmer([], $farm);
        $buyer = $this->buyer();
        $listing = $this->produce($farmer, 'Discounted Tomato');
        $rule = $this->discount($listing);
        $upcoming = $this->upcoming($farmer, 'Discounted Harvest');
        $this->discount($upcoming);

        $this->addToCart($buyer, $listing, 1);
        $this->checkout($buyer)->assertCreated();
        $placed = OrderItem::query()->where('listing_id', $listing->id)->firstOrFail();
        $this->assertEquals(5, (float) $placed->tawad_amount);

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $upcoming->id,
                'quantity' => 1,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();

        $held = Reservation::query()->where('listing_id', $upcoming->id)->firstOrFail();
        $this->assertEquals(5, (float) $held->tawad_amount);

        $farm->update(['tawad_enabled' => false]);

        $placed->refresh();
        $held->refresh();
        $this->assertEquals(5, (float) $placed->tawad_amount);
        $this->assertEquals(5, (float) $held->tawad_amount);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.tawad', null)
            ->assertJsonPath('data.tawad_paused', false);

        $this->asUser($farmer)
            ->getJson('/api/farmer/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.tawad_paused', true)
            ->assertJsonPath('data.tawad.id', $rule->id);

        $this->addToCart($buyer, $listing->fresh(), 1);

        $this->asUser($buyer)
            ->getJson('/api/buyer/cart')
            ->assertOk()
            ->assertJsonPath('data.0.tawad_amount', 0);

        $this->checkout($buyer)->assertCreated();
        $freshSale = OrderItem::query()->where('listing_id', $listing->id)->latest('id')->firstOrFail();
        $this->assertEquals(0, (float) $freshSale->tawad_amount);
        $this->assertEquals(5, (float) $placed->fresh()->tawad_amount);

        $another = $this->upcoming($farmer, 'Later Harvest');
        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $another->id,
                'quantity' => 1,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();

        $this->assertEquals(0, (float) Reservation::query()->where('listing_id', $another->id)->firstOrFail()->tawad_amount);

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'amount_received' => 40,
            ])
            ->assertCreated();

        $walkIn = Order::query()->where('source', OrderSource::WalkIn)->firstOrFail();
        $this->assertEquals(0, (float) $walkIn->tawad_total);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings/'.$listing->id.'/tawad', [
                'type' => TawadType::Flat->value,
                'discount_amount' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', Farm::TAWAD_OFF_MESSAGE);

        $this->asUser($farmer)
            ->deleteJson('/api/farmer/listings/'.$listing->id.'/tawad/'.$rule->id)
            ->assertOk();

        $this->assertFalse($rule->fresh()->is_active);

        $stranded = $this->produce($farmer, 'Stranded Tomato');
        $this->discount($stranded);
        $crop = $stranded->cropType;

        $ignored = app(FlagStrandedListingsAction::class)->floorRaised($farm, $crop, 10, 36);
        $this->assertSame([], $ignored);

        $farm->update(['tawad_enabled' => true]);
        $flagged = app(FlagStrandedListingsAction::class)->floorRaised($farm->fresh(), $crop, 10, 36);
        $this->assertSame([$stranded->id], $flagged);

        Carbon::setTestNow($upcoming->available_from->copy());
        $this->artisan('reservations:open-due')->assertSuccessful();

        $converted = OrderItem::query()->where('listing_id', $upcoming->id)->firstOrFail();
        $this->assertEquals(5, (float) $converted->tawad_amount);
    }

    public function test_walk_in_off_rejects_new_sales_and_keeps_past_ones(): void
    {
        $farm = Farm::factory()->create();
        $farmer = $this->farmer([], $farm);
        $listing = $this->produce($farmer, 'Stall Tomato');

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'amount_received' => 40,
            ])
            ->assertCreated();

        $past = Order::query()->where('source', OrderSource::WalkIn)->firstOrFail();

        $farm->update(['walk_in_enabled' => false]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'amount_received' => 40,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', Farm::WALK_IN_OFF_MESSAGE);

        $this->asUser($farmer)
            ->getJson('/api/farmer/orders')
            ->assertOk()
            ->assertJsonFragment(['id' => $past->id]);

        $this->assertSame(1, Order::query()->where('source', OrderSource::WalkIn)->count());
    }

    public function test_turning_the_switches_back_on_restores_each_feature(): void
    {
        $farm = Farm::factory()->create([
            'value_added_enabled' => false,
            'reservations_enabled' => false,
            'tawad_enabled' => false,
            'walk_in_enabled' => false,
        ]);
        $farmer = $this->farmer([], $farm);
        $buyer = $this->buyer();
        $jamType = $this->valueAddedCrop();
        $jam = $this->jam($farmer, 'Restored Jam', $jamType);
        $harvest = $this->upcoming($farmer, 'Restored Harvest');
        $produce = $this->produce($farmer, 'Restored Tomato');
        $rule = TawadRule::factory()->flat()->create(['listing_id' => $produce->id]);

        $this->asUser($buyer)->getJson('/api/buyer/marketplace/'.$jam->id)->assertNotFound();

        $farm->update([
            'value_added_enabled' => true,
            'reservations_enabled' => true,
            'tawad_enabled' => true,
            'walk_in_enabled' => true,
        ]);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$jam->id)
            ->assertOk();

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', $this->listingPayload($jamType, 'New Jam'))
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$harvest->id)
            ->assertOk()
            ->assertJsonPath('data.can_reserve', true);

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $harvest->id,
                'quantity' => 1,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$produce->id)
            ->assertOk()
            ->assertJsonPath('data.tawad.id', $rule->id);

        $this->addToCart($buyer, $produce, 1);
        $this->checkout($buyer)->assertCreated();
        $this->assertEquals(5, (float) OrderItem::query()->where('listing_id', $produce->id)->firstOrFail()->tawad_amount);

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $produce->id,
                'quantity' => 1,
                'amount_received' => 35,
            ])
            ->assertCreated();

        $this->assertEquals(5, (float) Order::query()->where('source', OrderSource::WalkIn)->firstOrFail()->tawad_total);
    }

    public function test_content_editors_toggle_their_own_farm_and_super_admins_toggle_any(): void
    {
        $own = Farm::factory()->create();
        $other = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $own);
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer([], $own);
        $buyer = $this->buyer();

        $this->assertCannotEdit($editor, $other);
        $this->assertCannotEdit($farmer, $own);
        $this->assertCannotEdit($buyer, $own);

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $own->getKey()])
            ->fillForm(['value_added_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($own->fresh()->value_added_enabled);
        $this->assertTrue($own->fresh()->reservations_enabled);

        $warning = json_encode(array_merge(
            session('filament.notifications', []),
            session('filament.claimed_notifications', []),
        ));

        $this->assertIsString($warning);
        $this->assertStringContainsString('Value-added products turned off', $warning);
        $this->assertStringContainsString(
            'Sellers can list processed goods (jams, chips, etc.). Off hides them from buyers.',
            $warning,
        );

        Livewire::actingAs($admin)
            ->test(EditFarm::class, ['record' => $other->getKey()])
            ->fillForm(['walk_in_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($other->fresh()->walk_in_enabled);
    }

    public function test_a_listing_with_no_farm_keeps_every_feature_on(): void
    {
        $farmer = $this->farmer();
        $listing = $this->produce($farmer, 'Unassigned Tomato');
        $rule = $this->discount($listing);
        $listing->farm_id = null;
        $listing->setRelation('farm', null);
        $listing->setRelation('activeTawadRule', $rule);

        $this->assertSame($rule->id, $listing->effectiveTawadRule()?->id);
        $this->assertTrue($listing->effectiveTawadRule() !== null);
    }

    /**
     * @return array{0: Farm, 1: User, 2: Farm, 3: User, 4: User, 5: CropType}
     */
    private function twoFarms(): array
    {
        $farmA = Farm::factory()->create([
            'value_added_enabled' => false,
            'reservations_enabled' => false,
            'tawad_enabled' => false,
        ]);
        $farmB = Farm::factory()->create();

        return [
            $farmA,
            $this->farmer([], $farmA),
            $farmB,
            $this->farmer([], $farmB),
            $this->buyer(),
            $this->valueAddedCrop(),
        ];
    }

    private function valueAddedCrop(): CropType
    {
        return $this->cropType(['category' => ProductCategory::ValueAdded]);
    }

    private function jam(User $farmer, string $title, ?CropType $cropType = null): Listing
    {
        return $this->produce($farmer, $title, $cropType ?? $this->valueAddedCrop());
    }

    private function produce(User $farmer, string $title, ?CropType $cropType = null): Listing
    {
        return $this->listingFor($farmer, [
            'title' => $title,
            'crop_type_id' => ($cropType ?? $this->cropType())->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 40,
            'quantity_available' => 20,
            'min_order_quantity' => 1,
            'order_step' => 1,
        ]);
    }

    private function upcoming(User $farmer, string $title, ?CropType $cropType = null): Listing
    {
        return $this->listingFor($farmer, [
            'title' => $title,
            'crop_type_id' => ($cropType ?? $this->cropType())->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 40,
            'quantity_available' => 20,
            'min_order_quantity' => 1,
            'order_step' => 1,
            'available_from' => now()->addDays(3),
            'available_until' => now()->addDays(10),
        ]);
    }

    private function discount(Listing $listing): TawadRule
    {
        return TawadRule::factory()->flat()->create([
            'listing_id' => $listing->id,
            'discount_amount' => 5,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listingPayload(CropType $cropType, string $title): array
    {
        return [
            'title' => $title,
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Kilogram->value,
            'price_per_unit' => 40,
            'quantity_available' => 10,
            'min_order_quantity' => 1,
            'order_step' => 1,
        ];
    }

    private function assertCannotEdit(User $actor, Farm $farm): void
    {
        try {
            Livewire::actingAs($actor)
                ->test(EditFarm::class, ['record' => $farm->getKey()])
                ->assertForbidden();
        } catch (ModelNotFoundException|AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
