<?php

namespace Tests\Feature\Api;

use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Enums\ListingStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\TawadRule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * Change A: farm-scoped, tighten-only price guards.
 *
 * Every test uses a crop type with a system floor of 25 and a system maximum
 * tawad of 20, the same values the rest of the suite uses, so the numbers can
 * be read against one baseline.
 */
class FarmPriceGuardTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    // Tighten-only

    public function test_a_farm_floor_below_the_system_floor_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->guard(Farm::factory()->create(), $this->kamatis(), floor: 24.99);
    }

    public function test_a_farm_ceiling_above_the_system_maximum_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->guard(Farm::factory()->create(), $this->kamatis(), ceiling: 20.01);
    }

    public function test_blank_on_both_sides_removes_the_override(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();

        $this->guard($farm, $cropType, floor: 35);
        $this->assertDatabaseHas('farm_crop_type_overrides', ['farm_id' => $farm->id]);

        $this->guard($farm, $cropType);
        $this->assertDatabaseMissing('farm_crop_type_overrides', ['farm_id' => $farm->id]);
    }

    // Enforcement on the effective values

    public function test_a_raised_farm_floor_rejects_a_listing_priced_below_it(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $farmer = $this->farmer(['email' => 'guarded@example.com'], $farm);
        $this->guard($farm, $cropType, floor: 35);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', $this->listingBody($cropType, price: 30))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_per_unit');

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', $this->listingBody($cropType, price: 35))
            ->assertCreated();
    }

    public function test_a_farm_floor_does_not_reach_another_farm(): void
    {
        $cropType = $this->kamatis();
        $this->guard(Farm::factory()->create(), $cropType, floor: 35);

        $otherFarmer = $this->farmer(['email' => 'unguarded@example.com'], Farm::factory()->create());

        $this->asUser($otherFarmer)
            ->postJson('/api/farmer/listings', $this->listingBody($cropType, price: 30))
            ->assertCreated();
    }

    public function test_a_lowered_farm_ceiling_rejects_a_tawad_above_it(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $farmer = $this->farmer(['email' => 'ceiling@example.com'], $farm);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $cropType->id, 'price_per_unit' => 40]);
        $this->guard($farm, $cropType, ceiling: 5);

        $this->asUser($farmer)
            ->postJson("/api/farmer/listings/{$listing->id}/tawad", [
                'type' => TawadType::Flat->value,
                'discount_amount' => 6,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_amount');

        $this->asUser($farmer)
            ->postJson("/api/farmer/listings/{$listing->id}/tawad", [
                'type' => TawadType::Flat->value,
                'discount_amount' => 5,
            ])
            ->assertCreated();
    }

    public function test_checkout_refuses_a_listing_stranded_by_a_farm_floor(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $farmer = $this->farmer(['email' => 'stranded@example.com'], $farm);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $cropType->id, 'price_per_unit' => 30]);
        $buyer = $this->buyer();

        $this->addToCart($buyer, $listing, 1);
        $this->guard($farm, $cropType, floor: 35);

        $this->checkout($buyer)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_skips_a_tawad_the_farm_ceiling_no_longer_allows(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $farmer = $this->farmer(['email' => 'skipped@example.com'], $farm);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $cropType->id, 'price_per_unit' => 40]);
        $this->rule($listing, 10);
        $buyer = $this->buyer();

        $this->addToCart($buyer, $listing, 1);
        $this->guard($farm, $cropType, ceiling: 5);

        // Skipped, not rejected: the order goes through at the listed price.
        $order = $this->checkout($buyer)->assertCreated()->json('data.0');

        $this->assertEquals(0, $order['tawad_total']);
        $this->assertEquals(40, $order['total']);
    }

    // The stranded-listing flag

    public function test_raising_a_farm_floor_notifies_newly_stranded_listings_only(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();

        $stranded = $this->listingFor(
            $this->farmer(['email' => 'stranded.seller@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 30],
        );
        $clear = $this->listingFor(
            $this->farmer(['email' => 'clear.seller@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 50],
        );
        $takenDown = $this->listingFor(
            $this->farmer(['email' => 'takendown.seller@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 30],
        );
        $takenDown->forceFill(['status' => ListingStatus::TakenDown, 'taken_down_at' => now()])->save();

        $this->guard($farm, $cropType, floor: 35);

        $this->assertSame(1, $this->notices($stranded->farmer_seller_id, NotificationType::FloorPriceRaised));
        $this->assertSame(0, $this->notices($clear->farmer_seller_id, NotificationType::FloorPriceRaised));
        $this->assertSame(0, $this->notices($takenDown->farmer_seller_id, NotificationType::FloorPriceRaised));

        // A second raise does not re-notify a listing already stranded by the first.
        $this->guard($farm, $cropType, floor: 40);

        $this->assertSame(1, $this->notices($stranded->farmer_seller_id, NotificationType::FloorPriceRaised));
    }

    public function test_a_raised_farm_floor_flags_stranded_listings_without_blocking_or_repricing(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $editor = $this->userWithRole(Role::ContentEditor, $farm);
        $below = $this->listingFor(
            $this->farmer(['email' => 'below.floor@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 30, 'title' => 'Priced under the new floor'],
        );
        $clear = $this->listingFor(
            $this->farmer(['email' => 'at.floor@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 35, 'title' => 'Priced at the new floor'],
        );

        $this->actingAs($editor);
        $this->assertTrue($editor->can('manageForFarm', [FarmCropTypeOverride::class, $farm]));

        $saved = $this->guard($farm, $cropType, floor: 35);

        $this->assertNotNull($saved);
        $this->assertDatabaseHas('farm_crop_type_overrides', [
            'farm_id' => $farm->id,
            'crop_type_id' => $cropType->id,
            'floor_price' => 35,
        ]);
        $this->assertSame('30.00', $below->refresh()->price_per_unit);
        $this->assertSame('35.00', $clear->refresh()->price_per_unit);

        $below->load(['cropType', 'farm.cropTypeOverrides']);
        $clear->load(['cropType', 'farm.cropTypeOverrides']);

        // 30 still clears the system floor of 25. The flag is the farm floor.
        $this->assertTrue($below->cropType->allowsPrice(30));
        $this->assertTrue($below->isBelowFloor());
        $this->assertSame(35.0, $below->effectiveFloor());

        $this->assertFalse($clear->isBelowFloor());
        $this->assertSame(35.0, $clear->effectiveFloor());

        $superAdmin = $this->userWithRole(Role::SuperAdmin);
        $this->actingAs($superAdmin);
        $this->assertTrue(ListingResource::canAccess());

        $visible = ListingResource::getEloquentQuery()->get();
        $flagged = $visible->filter(fn (Listing $listing): bool => $listing->isBelowFloor());

        $this->assertTrue($flagged->contains(fn (Listing $listing): bool => $listing->id === $below->id));
        $this->assertFalse($flagged->contains(fn (Listing $listing): bool => $listing->id === $clear->id));
        $this->assertTrue($visible->firstWhere('id', $below->id)->farm->relationLoaded('cropTypeOverrides'));
    }

    public function test_raising_a_farm_floor_flags_a_tawad_it_breaks(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $listing = $this->listingFor(
            $this->farmer(['email' => 'tawad.floor@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 40],
        );
        $this->rule($listing, 10);

        // 40 still clears 35, but 40 minus 10 does not.
        $this->guard($farm, $cropType, floor: 35);

        $body = InAppNotification::query()
            ->where('user_id', $listing->farmer_seller_id)
            ->where('type', NotificationType::FloorPriceRaised->value)
            ->value('body');

        $this->assertNotNull($body);
        $this->assertStringContainsString('tawad', $body);
    }

    public function test_lowering_a_farm_ceiling_notifies_a_tawad_left_above_it(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $listing = $this->listingFor(
            $this->farmer(['email' => 'tawad.ceiling@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 40],
        );
        $this->rule($listing, 10);

        $this->guard($farm, $cropType, ceiling: 5);

        $this->assertSame(1, $this->notices($listing->farmer_seller_id, NotificationType::TawadCeilingLowered));
    }

    public function test_one_change_to_both_values_sends_one_message_per_listing(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $listing = $this->listingFor(
            $this->farmer(['email' => 'both@example.com'], $farm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 30],
        );
        $this->rule($listing, 3);

        // Price stranded by the floor and rule stranded by the ceiling, in one write.
        $this->guard($farm, $cropType, floor: 35, ceiling: 2);

        $this->assertSame(1, $this->notices($listing->farmer_seller_id, NotificationType::FloorPriceRaised));
        $this->assertSame(0, $this->notices($listing->farmer_seller_id, NotificationType::TawadCeilingLowered));
    }

    public function test_raising_the_system_floor_notifies_each_farm_on_its_own_effective_floor(): void
    {
        $cropType = $this->kamatis();

        $plainFarm = Farm::factory()->create();
        $plain = $this->listingFor(
            $this->farmer(['email' => 'plain@example.com'], $plainFarm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 30],
        );

        // This farm already sits at 40, so a system raise to 35 changes nothing for it.
        $tightFarm = Farm::factory()->create();
        $this->guard($tightFarm, $cropType, floor: 40);
        $tight = $this->listingFor(
            $this->farmer(['email' => 'tight@example.com'], $tightFarm),
            ['crop_type_id' => $cropType->id, 'price_per_unit' => 45],
        );

        $cropType->update(['floor_price' => 35]);

        $this->assertSame(1, $this->notices($plain->farmer_seller_id, NotificationType::FloorPriceRaised));
        $this->assertSame(0, $this->notices($tight->farmer_seller_id, NotificationType::FloorPriceRaised));
    }

    // What the app is told

    public function test_the_seller_is_sent_their_farms_values_beside_the_system_ones(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $farmer = $this->farmer(['email' => 'contract@example.com'], $farm);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $cropType->id, 'price_per_unit' => 50]);
        $this->guard($farm, $cropType, floor: 35, ceiling: 5);

        // The tawad screen reads its guidance from the listing's crop type.
        $this->asUser($farmer)
            ->getJson("/api/farmer/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.crop_type.floor_price', 25)
            ->assertJsonPath('data.crop_type.max_discount', 20)
            ->assertJsonPath('data.crop_type.effective_floor_price', 35)
            ->assertJsonPath('data.crop_type.effective_max_discount', 5);
    }

    // Authorization

    public function test_a_content_editor_reaches_only_their_own_farms_guards(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $cropType = $this->kamatis();

        $editorA = $this->userWithRole(Role::ContentEditor, $farmA);
        $superAdmin = $this->userWithRole(Role::SuperAdmin);
        $farmer = $this->farmer(['email' => 'no.guards@example.com'], $farmA);

        $overrideOnB = $this->guard($farmB, $cropType, floor: 35);

        $this->assertTrue($editorA->can('manageForFarm', [FarmCropTypeOverride::class, $farmA]));
        $this->assertFalse($editorA->can('manageForFarm', [FarmCropTypeOverride::class, $farmB]));
        $this->assertFalse($editorA->can('update', $overrideOnB));

        $this->assertTrue($superAdmin->can('manageForFarm', [FarmCropTypeOverride::class, $farmB]));
        $this->assertTrue($superAdmin->can('update', $overrideOnB));

        $this->assertFalse($farmer->can('manageForFarm', [FarmCropTypeOverride::class, $farmA]));
    }

    public function test_a_content_editor_can_write_an_override_on_their_own_farm(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $editor = $this->userWithRole(Role::ContentEditor, $farm);

        $this->actingAs($editor);
        $this->assertTrue($editor->can('manageForFarm', [FarmCropTypeOverride::class, $farm]));

        $this->guard($farm, $cropType, floor: 45);

        $this->assertDatabaseHas('farm_crop_type_overrides', [
            'farm_id' => $farm->id,
            'crop_type_id' => $cropType->id,
            'floor_price' => 45,
        ]);
    }

    public function test_a_content_editor_cannot_write_an_override_on_another_farm(): void
    {
        $own = Farm::factory()->create();
        $other = Farm::factory()->create();
        $cropType = $this->kamatis();
        $editor = $this->userWithRole(Role::ContentEditor, $own);
        $existing = $this->guard($other, $cropType, floor: 30);

        $this->actingAs($editor);

        try {
            Gate::forUser($editor)->authorize('manageForFarm', [FarmCropTypeOverride::class, $other]);
            $this->guard($other, $cropType, floor: 45);
            $this->fail('A content editor must not write another farm.');
        } catch (AuthorizationException) {
            // The panel calls this same permission before SetFarmPriceOverrideAction.
        }

        $this->assertFalse($editor->can('update', $existing));
        $this->assertFalse($editor->can('delete', $existing));
        $this->assertNotSame((int) $editor->farm_id, (int) $other->id);
        $this->assertDatabaseHas('farm_crop_type_overrides', [
            'farm_id' => $other->id,
            'floor_price' => 30,
        ]);
        $this->assertDatabaseMissing('farm_crop_type_overrides', [
            'farm_id' => $other->id,
            'floor_price' => 45,
        ]);
    }

    public function test_a_super_admin_can_write_an_override_on_any_farm(): void
    {
        $farm = Farm::factory()->create();
        $cropType = $this->kamatis();
        $superAdmin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($superAdmin);
        $this->assertNull($superAdmin->farm_id);
        $this->assertTrue($superAdmin->can('manageForFarm', [FarmCropTypeOverride::class, $farm]));

        $this->guard($farm, $cropType, ceiling: 8);

        $this->assertDatabaseHas('farm_crop_type_overrides', [
            'farm_id' => $farm->id,
            'crop_type_id' => $cropType->id,
            'max_discount' => 8,
        ]);
    }

    public function test_neither_role_can_loosen_a_farm_guard(): void
    {
        $cropType = $this->kamatis();
        $editorFarm = Farm::factory()->create();
        $adminFarm = Farm::factory()->create();
        $editor = $this->userWithRole(Role::ContentEditor, $editorFarm);
        $superAdmin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($editor);
        $this->assertTrue($editor->can('manageForFarm', [FarmCropTypeOverride::class, $editorFarm]));
        $this->assertLooseningRejected($editorFarm, $cropType);

        $this->actingAs($superAdmin);
        $this->assertTrue($superAdmin->can('manageForFarm', [FarmCropTypeOverride::class, $adminFarm]));
        $this->assertLooseningRejected($adminFarm, $cropType);
    }

    // Helpers. Named apart from CreatesMarketplaceActors so none shadow it.

    private function assertLooseningRejected(Farm $farm, CropType $cropType): void
    {
        foreach ([[24.99, null], [null, 20.01]] as [$floor, $ceiling]) {
            try {
                $this->guard($farm, $cropType, floor: $floor, ceiling: $ceiling);
                $this->fail('A farm guard must not loosen the system floor or the system maximum.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseMissing('farm_crop_type_overrides', [
            'farm_id' => $farm->id,
        ]);
    }

    private function kamatis(): CropType
    {
        return $this->cropType(['floor_price' => 25, 'max_discount' => 20]);
    }

    private function guard(Farm $farm, CropType $cropType, ?float $floor = null, ?float $ceiling = null): ?FarmCropTypeOverride
    {
        return app(SetFarmPriceOverrideAction::class)->execute($farm, $cropType, $floor, $ceiling);
    }

    private function rule(Listing $listing, float $amount): TawadRule
    {
        return TawadRule::factory()->for($listing)->create([
            'type' => TawadType::Flat,
            'discount_amount' => $amount,
            'min_quantity' => null,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listingBody(CropType $cropType, float $price): array
    {
        return [
            'title' => 'Fresh kamatis, hand picked',
            'crop_type_id' => $cropType->id,
            'price_per_unit' => $price,
            'quantity_available' => 20,
        ];
    }

    private function notices(int $userId, NotificationType $type): int
    {
        return InAppNotification::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->count();
    }

    private function userWithRole(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->assignRole($role->value);

        return $user;
    }
}
