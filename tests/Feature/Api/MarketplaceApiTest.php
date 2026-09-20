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
}
