<?php

namespace Tests\Feature;

use App\Models\FarmCropTypeOverride;
use App\Models\Listing;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CropTypeDeletionTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_deleting_a_crop_type_removes_its_listings_and_keeps_the_order(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType(['name' => 'Kamatis']);
        $otherCrop = $this->cropType(['name' => 'Kamote']);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'title' => 'Ripe tomatoes',
            'price_per_unit' => 40,
        ]);
        $removedListing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'title' => 'Already taken down',
            'price_per_unit' => 40,
        ]);
        $removedListing->delete();
        $keptListing = $this->listingFor($farmer, [
            'crop_type_id' => $otherCrop->id,
            'title' => 'Sweet potato',
            'price_per_unit' => 40,
        ]);
        $order = $this->placeOrder($this->buyer(), $listing, 1);
        $item = $order->items()->firstOrFail();

        FarmCropTypeOverride::query()->create([
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $cropType->id,
            'floor_price' => 30,
            'max_discount' => 5,
        ]);

        $cropType->delete();

        $this->assertModelMissing($cropType);
        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
        $this->assertNull(Listing::withTrashed()->find($removedListing->id));
        $this->assertDatabaseMissing('farm_crop_type_overrides', [
            'crop_type_id' => $cropType->id,
        ]);
        $this->assertModelExists($keptListing);
        $this->assertModelExists($order);
        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'order_id' => $order->id,
            'listing_name' => 'Ripe tomatoes',
            'line_total' => $item->line_total,
            'crop_type_id' => null,
            'listing_id' => null,
        ]);
    }
}
