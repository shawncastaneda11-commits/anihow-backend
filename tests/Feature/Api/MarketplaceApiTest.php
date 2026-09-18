<?php

namespace Tests\Feature\Api;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Review;
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

    public function test_buyer_can_sort_marketplace_by_price_availability_and_freshest(): void
    {
        $farmer = $this->farmer();
        $oldestCheap = Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Sitaw',
            'price_per_unit' => 20,
            'quantity_available' => 1,
            'created_at' => now()->subDays(2),
        ]);
        $newestExpensive = Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Mango',
            'price_per_unit' => 90,
            'quantity_available' => 40,
            'created_at' => now()->subHour(),
        ]);
        Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Tomato',
            'price_per_unit' => 50,
            'quantity_available' => 10,
            'created_at' => now()->subDay(),
        ]);

        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Sitaw')
            ->assertJsonPath('data.2.name', 'Mango');

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace?sort=price_desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Mango')
            ->assertJsonPath('data.2.name', 'Sitaw');

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace?sort=availability')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Mango')
            ->assertJsonPath('data.2.name', 'Sitaw');

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace?sort=freshest')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newestExpensive->id)
            ->assertJsonPath('data.2.id', $oldestCheap->id);
    }

    public function test_returns_422_when_marketplace_sort_is_invalid(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);

        $this->withToken($buyer->createToken('mobile')->plainTextToken)
            ->getJson('/api/buyer/marketplace?sort=delivery')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_marketplace_listings_include_seller_average_rating_from_reviews(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create(['name' => 'Tomato']);
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $reviewer = User::factory()->create();
        $reviewer->assignRole(Role::Buyer);
        $reservation = Reservation::factory()->create([
            'buyer_id' => $reviewer->id,
            'farmer_seller_id' => $farmer->id,
            'status' => ReservationStatus::Completed,
        ]);
        Review::factory()->create([
            'buyer_id' => $reviewer->id,
            'farmer_seller_id' => $farmer->id,
            'reservation_id' => $reservation->id,
            'rating' => 4,
        ]);

        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace')
            ->assertOk()
            ->assertJsonPath('data.0.average_rating', '4.0')
            ->assertJsonPath('data.0.reviews_count', 1)
            ->assertJsonPath('data.0.seller.average_rating', '4.0');

        $this->withToken($token)
            ->getJson("/api/buyer/marketplace/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.average_rating', '4.0')
            ->assertJsonPath('data.seller.average_rating', '4.0')
            ->assertJsonPath('data.seller.id', $farmer->id);
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
