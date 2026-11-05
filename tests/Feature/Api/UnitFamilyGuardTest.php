<?php

namespace Tests\Feature\Api;

use App\Actions\Pricing\ChangeCropTypeUnitAction;
use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Enums\ListingUnit;
use App\Enums\NotificationType;
use App\Enums\TawadType;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\FarmCropTypeOverride;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * A crop's own floor and maximum tawad cover its main unit and every unit
 * that converts to it. An extra row (CropTypeUnitGuard) adds limits for one
 * more unit family, so sitaw guarded per kg can also be sold per bundle.
 */
class UnitFamilyGuardTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_crop_guarded_per_kg_can_be_listed_per_bundle_with_a_bundle_row(): void
    {
        $crop = $this->sitaw();
        $farmer = $this->farmer();

        $this->createListing($farmer, $crop, ListingUnit::Bundle, 20)->assertCreated();
        $this->createListing($farmer, $crop, ListingUnit::Kilogram, 45)->assertCreated();
    }

    public function test_a_bundle_listing_is_held_to_the_bundle_floor(): void
    {
        $farmer = $this->farmer();

        $this->createListing($farmer, $this->sitaw(), ListingUnit::Bundle, 12)
            ->assertUnprocessable()
            ->assertJsonPath('errors.price_per_unit.0', 'The floor price for Sitaw is PHP 15.00 per bundle.');
    }

    public function test_a_unit_with_no_limits_is_refused_and_the_message_names_the_allowed_units(): void
    {
        $farmer = $this->farmer();

        $this->createListing($farmer, $this->sitaw(), ListingUnit::Sack, 500)
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit.0', "This crop's floor price is per kg, so it can be sold per g, kg or bundle.");
    }

    public function test_a_bulk_tawad_on_a_bundle_listing_is_judged_against_the_bundle_row(): void
    {
        $crop = $this->sitaw();
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Bundle, 20);

        // PHP 18 off 3 bundles is PHP 6 per bundle, over the PHP 5 bundle maximum.
        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 18, 3)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.discount_amount.0',
                'The maximum tawad for Sitaw is PHP 5.00 per bundle. This tawad works out to PHP 6.00 per bundle.',
            );

        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 15, 3)->assertCreated();
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 3);
        $order = $this->checkout($buyer)->assertCreated()->json('data.0');

        $this->assertEquals(15, $order['tawad_total']);
        $this->assertEquals(45, $order['total']);
    }

    public function test_a_dozen_listing_converts_to_a_piece_row_on_a_kg_crop(): void
    {
        $crop = $this->crop('Kalabasa', ListingUnit::Kilogram, floor: 30, max: 5);
        CropTypeUnitGuard::factory()->for($crop)->create([
            'unit' => ListingUnit::Piece,
            'floor_price' => 8,
            'max_discount' => 2,
        ]);
        $farmer = $this->farmer();

        // PHP 8 per piece is PHP 96 per dozen.
        $this->createListing($farmer, $crop, ListingUnit::Dozen, 90)
            ->assertUnprocessable()
            ->assertJsonPath('errors.price_per_unit.0', 'The floor price for Kalabasa is PHP 8.00 per piece.');
        $this->createListing($farmer, $crop, ListingUnit::Dozen, 100)->assertCreated();
    }

    public function test_the_seller_sees_the_bundle_limits_on_the_listing(): void
    {
        [$farmer, $listing] = $this->listingOf($this->sitaw(), ListingUnit::Bundle, 20);

        $this->asUser($farmer)
            ->getJson("/api/farmer/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.price_guard.unit', 'bundle')
            ->assertJsonPath('data.price_guard.floor_per_unit', 15)
            ->assertJsonPath('data.price_guard.max_tawad_per_unit', 5)
            ->assertJsonPath('data.price_guard.unit_allowed', true);
    }

    public function test_the_crop_type_lists_its_extra_units(): void
    {
        $crop = $this->sitaw();

        $response = $this->asUser($this->farmer())->getJson('/api/crop-types')->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $crop->id);

        $this->assertSame([['unit' => 'bundle', 'floor_price' => 15, 'max_discount' => 5]], $row['unit_guards']);
        $this->assertContains('bundle', array_column($row['allowed_units'], 'value'));
        $this->assertNotContains('sack', array_column($row['allowed_units'], 'value'));
    }

    public function test_buyers_see_bundle_listings_but_not_units_without_limits(): void
    {
        $crop = $this->sitaw();
        [, $bundle] = $this->listingOf($crop, ListingUnit::Bundle, 20);
        [, $sack] = $this->listingOf($crop, ListingUnit::Sack, 500);

        $visible = Listing::query()->buyerVisible()->pluck('id');

        $this->assertTrue($visible->contains($bundle->id));
        $this->assertFalse($visible->contains($sack->id));
    }

    public function test_a_farm_override_for_bundles_tightens_bundles_only(): void
    {
        $crop = $this->sitaw();
        $farmer = $this->farmer();

        app(SetFarmPriceOverrideAction::class)->execute($farmer->farm, $crop, 18, null, ListingUnit::Bundle);

        $this->createListing($farmer, $crop, ListingUnit::Bundle, 16)
            ->assertUnprocessable()
            ->assertJsonPath('errors.price_per_unit.0', 'The floor price for Sitaw is PHP 18.00 per bundle.');
        // The kg floor is still the crop's own PHP 40.
        $this->createListing($farmer, $crop, ListingUnit::Kilogram, 41)->assertCreated();
    }

    public function test_a_farm_override_for_a_unit_with_no_row_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(SetFarmPriceOverrideAction::class)->execute($this->farm(), $this->sitaw(), 600, null, ListingUnit::Sack);
    }

    public function test_raising_the_bundle_floor_flags_bundle_listings_only(): void
    {
        $crop = $this->sitaw();
        [$farmer, $bundle] = $this->listingOf($crop, ListingUnit::Bundle, 16);
        $kilo = $this->listingFor($farmer, ['crop_type_id' => $crop->id, 'unit' => ListingUnit::Kilogram, 'price_per_unit' => 41]);

        $crop->unitGuards()->first()->update(['floor_price' => 17]);

        $flagged = Listing::query()
            ->whereHas('cropType', fn ($query) => $query->whereRaw(ListingsTable::belowFloorSql()))
            ->pluck('id');

        $this->assertTrue($flagged->contains($bundle->id));
        $this->assertFalse($flagged->contains($kilo->id));
        $this->assertTrue($bundle->fresh(['cropType'])->isBelowFloor());
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $farmer->id,
            'type' => NotificationType::FloorPriceRaised->value,
        ]);
        $this->assertDatabaseCount('in_app_notifications', 1);
    }

    public function test_removing_the_bundle_row_clears_its_farm_overrides_and_hides_bundle_listings(): void
    {
        $crop = $this->sitaw();
        [$farmer, $bundle] = $this->listingOf($crop, ListingUnit::Bundle, 20);
        app(SetFarmPriceOverrideAction::class)->execute($farmer->farm, $crop, 18, null, ListingUnit::Bundle);
        app(SetFarmPriceOverrideAction::class)->execute($farmer->farm, $crop, 42, null);

        $crop->unitGuards()->first()->delete();

        $this->assertFalse(Listing::query()->buyerVisible()->pluck('id')->contains($bundle->id));
        $this->assertSame(1, FarmCropTypeOverride::query()->where('crop_type_id', $crop->id)->count());
        $this->assertNull(FarmCropTypeOverride::query()->where('crop_type_id', $crop->id)->first()->unit);
    }

    public function test_the_main_unit_cannot_move_into_an_extra_rows_family(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Tray, floor: 200, max: null);
        CropTypeUnitGuard::factory()->for($crop)->create(['unit' => ListingUnit::Piece, 'floor_price' => 8, 'max_discount' => null]);

        $this->expectException(ValidationException::class);

        app(ChangeCropTypeUnitAction::class)->update($crop, ['unit_of_measure' => ListingUnit::Dozen->value]);
    }

    private function sitaw(): CropType
    {
        $crop = $this->crop('Sitaw', ListingUnit::Kilogram, floor: 40, max: 5);
        CropTypeUnitGuard::factory()->for($crop)->create([
            'unit' => ListingUnit::Bundle,
            'floor_price' => 15,
            'max_discount' => 5,
        ]);

        return $crop;
    }

    private function crop(string $name, ListingUnit $unit, float $floor, ?float $max): CropType
    {
        return $this->cropType([
            'name' => $name,
            'unit_of_measure' => $unit,
            'floor_price' => $floor,
            'max_discount' => $max,
        ]);
    }

    /**
     * @return array{0: User, 1: Listing}
     */
    private function listingOf(CropType $crop, ListingUnit $unit, float $price): array
    {
        $farmer = $this->farmer();

        return [$farmer, $this->listingFor($farmer, [
            'crop_type_id' => $crop->id,
            'unit' => $unit,
            'price_per_unit' => $price,
        ])];
    }

    private function createListing(User $farmer, CropType $crop, ListingUnit $unit, float $price): TestResponse
    {
        return $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => "{$crop->name} per {$unit->value}",
            'crop_type_id' => $crop->id,
            'unit' => $unit->value,
            'price_per_unit' => $price,
            'quantity_available' => 20,
        ]);
    }

    private function tawad(User $farmer, Listing $listing, TawadType $type, float $amount, ?float $minQuantity = null): TestResponse
    {
        return $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/tawad", array_filter([
            'type' => $type->value,
            'discount_amount' => $amount,
            'min_quantity' => $minQuantity,
        ], fn (mixed $value): bool => $value !== null));
    }
}
