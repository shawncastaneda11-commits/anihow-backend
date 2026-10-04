<?php

namespace Tests\Feature;

use App\Actions\Pricing\ChangeCropTypeUnitAction;
use App\Enums\ListingUnit;
use App\Enums\NotificationType;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\FarmCropTypeOverride;
use App\Models\InAppNotification;
use App\Models\Listing;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ListingUnitEdgeTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_changing_kilograms_to_grams_converts_the_floor_and_keeps_a_sixty_peso_listing_legal(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 60,
        ]);
        FarmCropTypeOverride::query()->create([
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $cropType->id,
            'floor_price' => 40,
            'max_discount' => 10,
        ]);

        app(ChangeCropTypeUnitAction::class)->update($cropType, [
            'unit_of_measure' => ListingUnit::Gram->value,
        ]);

        $cropType->refresh();
        $override = FarmCropTypeOverride::query()->where('crop_type_id', $cropType->id)->first();

        $this->assertSame(ListingUnit::Gram, $cropType->unit_of_measure);
        $this->assertEquals(0.05, (float) $cropType->floor_price);
        $this->assertEquals(0.01, (float) $cropType->max_discount);
        $this->assertEquals(0.04, (float) $override->floor_price);
        $this->assertEquals(0.01, (float) $override->max_discount);
        $this->assertEquals(60.0, (float) $listing->refresh()->price_per_unit);
        $this->assertFalse($listing->fresh(['cropType', 'farm.cropTypeOverrides'])->isBelowFloor());

        $this->asUser($farmer)
            ->patchJson("/api/farmer/listings/{$listing->id}", [
                'unit' => 'kg',
                'price_per_unit' => 60,
            ])
            ->assertOk();
    }

    public function test_a_cross_family_unit_change_is_refused_when_listings_exist(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);
        $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 60,
        ]);

        try {
            app(ChangeCropTypeUnitAction::class)->update($cropType, [
                'unit_of_measure' => ListingUnit::Tray->value,
            ]);
            $this->fail('A cross-family unit change should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'can only change to kg or g',
                $exception->errors()['unit_of_measure'][0],
            );
        }

        $this->assertSame(ListingUnit::Kilogram, $cropType->refresh()->unit_of_measure);
        $this->assertEquals(50.0, (float) $cropType->floor_price);
    }

    public function test_a_crop_with_no_listings_or_overrides_can_change_family_without_rescaling(): void
    {
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);

        app(ChangeCropTypeUnitAction::class)->update($cropType, [
            'unit_of_measure' => ListingUnit::Tray->value,
        ]);

        $cropType->refresh();

        $this->assertSame(ListingUnit::Tray, $cropType->unit_of_measure);
        $this->assertEquals(50.0, (float) $cropType->floor_price);
        $this->assertEquals(10.0, (float) $cropType->max_discount);
    }

    public function test_a_tray_listing_is_stranded_when_a_per_kilogram_floor_is_added(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $cropType = $this->cropType([
            'name' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 0,
            'max_discount' => 0,
        ]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Tray,
            'price_per_unit' => 80,
            'title' => 'Kamatis by the tray',
        ]);

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertOk();

        $this->addToCart($buyer, $listing, 1);

        $cropType->update([
            'floor_price' => 50,
            'max_discount' => 10,
        ]);

        $body = InAppNotification::query()
            ->where('user_id', $farmer->id)
            ->where('type', NotificationType::FloorPriceRaised->value)
            ->value('body');

        $this->assertNotNull($body);
        $this->assertStringContainsString('per g or kg', $body);

        $this->assertTrue($listing->fresh(['cropType', 'farm.cropTypeOverrides'])->isBelowFloor());

        $listed = collect(
            $this->asUser($buyer)->getJson('/api/buyer/marketplace')->json('data'),
        )->pluck('id');

        $this->assertFalse($listed->contains($listing->id));
        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertNotFound();

        $checkout = $this->checkout($buyer);
        $checkout->assertUnprocessable();
        $this->assertStringContainsString('sold per tray', json_encode($checkout->json()));

        $flagged = Listing::query()
            ->whereHas('cropType', fn ($crop) => $crop->whereRaw(ListingsTable::belowFloorSql()))
            ->pluck('id');

        $this->assertTrue($flagged->contains($listing->id));
    }

    public function test_a_small_gram_price_checks_out_at_whole_centavos_and_clears_a_kilogram_floor(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 50,
            'max_discount' => 10,
        ]);
        $grams = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Gram,
            'price_per_unit' => 0.055,
            'quantity_available' => 600,
        ]);
        $ordinaryCrop = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 20,
            'max_discount' => 5,
        ]);
        $kilos = $this->listingFor($farmer, [
            'crop_type_id' => $ordinaryCrop->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 30,
            'quantity_available' => 10,
        ]);

        $small = $this->placeOrder($this->buyer(), $grams, 500);
        $ordinary = $this->placeOrder($this->buyer(), $kilos, 2);

        $this->assertSame('27.50', $small->total);
        $this->assertSame('27.50', $small->items()->first()->line_total);
        $this->assertEquals(0.055, (float) $small->items()->first()->unit_price);
        $this->assertSame(ListingUnit::Gram, $small->items()->first()->unit);
        $this->assertSame('60.00', $ordinary->total);
    }
}
