<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_can_browse_filter_search_and_view_active_listings(): void
    {
        $farmer = $this->farmer([
            'name' => 'Juan Dela Cruz',
            'location' => 'San Francisco, General Trias, Cavite',
        ]);
        $vegetables = Category::factory()->create(['name' => 'Vegetables', 'slug' => 'vegetables']);
        $fruit = Category::factory()->create(['name' => 'Fruit', 'slug' => 'fruit']);

        $tomato = Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Tomato',
            'category_id' => $vegetables->id,
        ]);
        Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Mango',
            'category_id' => $fruit->id,
        ]);
        Listing::factory()->forFarmer($farmer)->inactive()->create([
            'name' => 'Hidden Sitaw',
            'category_id' => $vegetables->id,
        ]);

        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['name' => 'Hidden Sitaw']);

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace?category_id='.$vegetables->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tomato');

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace?search=tom')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tomato');

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace/'.$tomato->id)
            ->assertOk()
            ->assertJsonPath('data.seller.name', 'Juan Dela Cruz')
            ->assertJsonPath('data.seller.location', 'San Francisco, General Trias, Cavite');

        $this->withToken($token)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Fruit');
    }

    public function test_buyer_cannot_view_an_inactive_listing(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->inactive()->create();

        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);

        $this->withToken($buyer->createToken('mobile')->plainTextToken)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertNotFound();
    }

    public function test_farmer_cannot_browse_the_buyer_marketplace(): void
    {
        $farmer = $this->farmer();

        $this->withToken($farmer->createToken('mobile')->plainTextToken)
            ->getJson('/api/buyer/marketplace')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function farmer(array $attributes = []): User
    {
        $farmer = User::factory()->create($attributes);
        $farmer->assignRole(Role::FarmerSeller);

        return $farmer;
    }
}
