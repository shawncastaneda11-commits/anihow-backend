<?php

namespace Tests\Feature;

use App\Enums\ListingUnit;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\FarmCropTypeOverride;
use App\Models\Listing;
use App\Support\Pricing\UnitConverter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ListingUnitTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_gram_listing_passes_when_its_converted_price_meets_a_per_kilogram_floor(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Kamatis, per gram',
                'crop_type_id' => $cropType->id,
                'unit' => 'g',
                'price_per_unit' => 0.06,
                'quantity_available' => 500,
            ])
            ->assertCreated()
            ->assertJsonPath('data.unit', 'g')
            ->assertJsonPath('data.unit_label', 'Gram (g)');

        $allowed = collect($this->asUser($farmer)->getJson('/api/crop-types')->json('data'))
            ->firstWhere('id', $cropType->id);

        $this->assertSame(
            ['g', 'kg'],
            collect($allowed['allowed_units'])->pluck('value')->all(),
        );
    }

    public function test_a_gram_listing_fails_when_its_converted_price_is_below_the_floor(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Kamatis, too cheap',
                'crop_type_id' => $cropType->id,
                'unit' => 'g',
                'price_per_unit' => 0.04,
                'quantity_available' => 500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_per_unit']);
    }

    public function test_a_tray_listing_is_refused_on_a_per_kilogram_crop_with_a_floor(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Kamatis, by the tray',
                'crop_type_id' => $cropType->id,
                'unit' => 'tray',
                'price_per_unit' => 80,
                'quantity_available' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.unit.0',
                "This crop's floor price is per kg, so it can be sold per g or kg.",
            );
    }

    public function test_any_unit_is_allowed_when_the_crop_has_no_guards(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 0,
            'max_discount' => 0,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Kamatis, by the tray',
                'crop_type_id' => $cropType->id,
                'unit' => 'tray',
                'price_per_unit' => 80,
                'quantity_available' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.unit', 'tray');
    }

    public function test_a_farm_floor_override_limits_units_when_the_system_floor_is_zero(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 0,
            'max_discount' => 0,
        ]);

        FarmCropTypeOverride::query()->create([
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $cropType->id,
            'floor_price' => 40,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Kamatis, by the tray',
                'crop_type_id' => $cropType->id,
                'unit' => 'tray',
                'price_per_unit' => 80,
                'quantity_available' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit']);
    }

    public function test_checkout_converts_before_the_floor_check_and_stores_the_listing_unit(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Gram,
            'price_per_unit' => 0.06,
            'quantity_available' => 20,
        ]);

        $order = $this->placeOrder($this->buyer(), $listing, 2);
        $item = $order->items()->first();

        $this->assertSame(ListingUnit::Gram, $item->unit);
        $this->assertEquals(0.06, (float) $item->unit_price);

        $cheap = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Gram,
            'price_per_unit' => 0.04,
            'quantity_available' => 20,
        ]);
        $buyer = $this->buyer();

        $this->addToCart($buyer, $cheap, 1);
        $this->checkout($buyer)->assertUnprocessable();
    }

    public function test_analytics_adds_grams_and_kilograms_and_keeps_trays_separate(): void
    {
        $farmer = $this->farmer();
        $kamatis = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);
        $eggs = $this->cropType([
            'name' => 'Itlog',
            'unit_of_measure' => ListingUnit::Tray,
            'floor_price' => 10,
            'max_discount' => 2,
        ]);

        $grams = $this->listingFor($farmer, [
            'crop_type_id' => $kamatis->id,
            'unit' => ListingUnit::Gram,
            'price_per_unit' => 0.06,
            'quantity_available' => 600,
        ]);
        $kilos = $this->listingFor($farmer, [
            'crop_type_id' => $kamatis->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 60,
            'quantity_available' => 10,
        ]);
        $trays = $this->listingFor($farmer, [
            'crop_type_id' => $eggs->id,
            'unit' => ListingUnit::Tray,
            'price_per_unit' => 20,
            'quantity_available' => 10,
        ]);

        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $grams, 500), 30);
        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $kilos, 1), 60);
        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $trays, 4), 80);

        $rows = collect(
            $this->asUser($farmer)->getJson('/api/farmer/analytics')->assertOk()->json('data.units_per_crop_type'),
        );

        $tomato = $rows->firstWhere('crop', 'Kamatis');
        $tray = $rows->firstWhere('crop', 'Itlog');

        $this->assertSame('kg', $tomato['unit']);
        $this->assertEqualsWithDelta(1.5, (float) $tomato['units'], 0.001);
        $this->assertSame('tray', $tray['unit']);
        $this->assertEquals(4.0, (float) $tray['units']);
        $this->assertCount(2, $rows);
    }

    public function test_existing_listings_are_backfilled_from_the_crop_type_unit(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Tray,
            'floor_price' => 10,
            'max_discount' => 0,
        ]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 20,
        ]);

        app(UnitConverter::class)->backfillListingUnits();

        $this->assertSame(ListingUnit::Tray, $listing->fresh()->unit);
    }

    public function test_the_priced_below_floor_filter_converts_grams_into_kilograms(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);
        $clearGram = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Gram,
            'price_per_unit' => 0.06,
        ]);
        $belowGram = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Gram,
            'price_per_unit' => 0.04,
        ]);
        $belowKilo = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 40,
        ]);

        $flagged = Listing::query()
            ->whereHas('cropType', fn ($crop) => $crop->whereRaw(ListingsTable::belowFloorSql()))
            ->pluck('id');

        $this->assertFalse($flagged->contains($clearGram->id));
        $this->assertTrue($flagged->contains($belowGram->id));
        $this->assertTrue($flagged->contains($belowKilo->id));
        $this->assertTrue($belowGram->fresh(['cropType'])->isBelowFloor());
        $this->assertFalse($clearGram->fresh(['cropType'])->isBelowFloor());
    }
}
