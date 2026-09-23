<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_marketplace_filters_by_crop_type_id(): void
    {
        $farmer = $this->farmer();
        $kamatis = $this->cropType(['name' => 'Kamatis', 'slug' => 'kamatis-filter']);
        $kamote = $this->cropType(['name' => 'Kamote', 'slug' => 'kamote-filter']);

        $this->listingFor($farmer, [
            'title' => 'Fresh kamatis',
            'crop_type_id' => $kamatis->id,
        ]);
        $this->listingFor($farmer, [
            'title' => 'Morning kamote',
            'crop_type_id' => $kamote->id,
        ]);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $filtered = $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?crop_type_id='.$kamatis->id)
            ->assertOk();

        $filtered->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Fresh kamatis')
            ->assertJsonPath('data.0.crop_type.id', $kamatis->id);
    }

    public function test_marketplace_hides_inactive_listings_and_rejects_an_invalid_sort(): void
    {
        $farmer = $this->farmer();
        $hidden = $this->listingFor($farmer, [
            'title' => 'Hidden sitaw',
            'is_active' => false,
        ]);
        $this->listingFor($farmer, ['title' => 'Visible kamatis']);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visible kamatis');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$hidden->id)
            ->assertNotFound();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?sort=delivery')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_farmer_cannot_browse_the_buyer_marketplace(): void
    {
        $this->asUser($this->farmer())
            ->getJson('/api/buyer/marketplace')
            ->assertForbidden();
    }

    public function test_marketplace_search_matching_nothing_returns_empty(): void
    {
        $farmerA = $this->farmer(farm: $this->farm());
        $farmerB = $this->farmer(farm: $this->farm());
        $kamatis = $this->cropType(['name' => 'Kamatis', 'slug' => 'kamatis-search-none']);
        $kamote = $this->cropType(['name' => 'Kamote', 'slug' => 'kamote-search-none']);

        $this->listingFor($farmerA, [
            'title' => 'Fresh kamatis',
            'crop_type_id' => $kamatis->id,
        ]);
        $this->listingFor($farmerB, [
            'title' => 'Morning kamote',
            'crop_type_id' => $kamote->id,
        ]);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?search=zxqv9notafarm')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_marketplace_search_matching_farm_name_returns_only_that_farm(): void
    {
        $farmA = $this->farm();
        $farmA->update(['name' => 'Santos Farmstead']);
        $farmB = $this->farm();
        $farmB->update(['name' => 'Reyes Homestead']);

        $farmerA = $this->farmer(farm: $farmA);
        $farmerB = $this->farmer(farm: $farmB);
        $kamatis = $this->cropType(['name' => 'Kamatis', 'slug' => 'kamatis-search-farm']);

        $this->listingFor($farmerA, [
            'title' => 'Morning crate',
            'crop_type_id' => $kamatis->id,
        ]);
        $this->listingFor($farmerB, [
            'title' => 'Afternoon crate',
            'crop_type_id' => $kamatis->id,
        ]);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?search=Santos Farmstead')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Morning crate')
            ->assertJsonPath('data.0.farm.name', 'Santos Farmstead');
    }

    public function test_marketplace_search_matching_seller_shop_name_returns_only_that_seller(): void
    {
        $farmA = $this->farm();
        $farmA->update(['name' => 'North Communal Plot']);
        $farmB = $this->farm();
        $farmB->update(['name' => 'South Communal Plot']);

        $farmerA = $this->farmer(['shop_name' => 'Mang Tonyo Farm'], farm: $farmA);
        $farmerB = $this->farmer(['shop_name' => 'Aling Nena Produce'], farm: $farmB);
        $kamatis = $this->cropType(['name' => 'Kamatis', 'slug' => 'kamatis-search-shop']);

        $this->listingFor($farmerA, [
            'title' => 'Morning crate',
            'crop_type_id' => $kamatis->id,
        ]);
        $this->listingFor($farmerB, [
            'title' => 'Afternoon crate',
            'crop_type_id' => $kamatis->id,
        ]);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?search=Mang Tonyo Farm')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Morning crate')
            ->assertJsonPath('data.0.seller.shop_name', 'Mang Tonyo Farm');
    }

    public function test_marketplace_search_matching_listing_title_returns_only_that_listing(): void
    {
        $farm = $this->farm();
        $farm->update(['name' => 'Manggahan Plot']);
        $farmer = $this->farmer(farm: $farm);
        $kamatis = $this->cropType(['name' => 'Kamatis', 'slug' => 'kamatis-search-title']);

        $this->listingFor($farmer, [
            'title' => 'Sunset sitaw bundle',
            'crop_type_id' => $kamatis->id,
        ]);
        $this->listingFor($farmer, [
            'title' => 'Dawn talong crate',
            'crop_type_id' => $kamatis->id,
        ]);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?search=Sunset')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sunset sitaw bundle');
    }
}
