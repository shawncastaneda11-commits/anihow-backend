<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_farmer_can_record_a_walk_in_sale_and_stock_decrements(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Tomato',
            'quantity_available' => 10,
            'price_per_unit' => 65,
        ]);
        $token = $farmer->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/farmer/sales', [
            'notes' => 'Walk-in cash',
            'items' => [
                ['listing_id' => $listing->id, 'quantity' => 1.5],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.total', '97.50');

        $this->assertEquals('8.50', $listing->fresh()->quantity_available);

        $this->withToken($token)
            ->getJson('/api/farmer/sales')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pos_cannot_sell_another_farmers_listing_or_oversell(): void
    {
        $farmer = $this->farmer(['email' => 'juan@example.com']);
        $other = $this->farmer(['email' => 'maria@example.com']);
        $own = Listing::factory()->forFarmer($farmer)->create(['quantity_available' => 1]);
        $theirs = Listing::factory()->forFarmer($other)->create(['quantity_available' => 10]);
        $token = $farmer->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/farmer/sales', [
            'items' => [['listing_id' => $theirs->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->withToken($token)->postJson('/api/farmer/sales', [
            'items' => [['listing_id' => $own->id, 'quantity' => 3]],
        ])->assertUnprocessable();

        $this->assertEquals('1.00', $own->fresh()->quantity_available);
        $this->assertEquals('10.00', $theirs->fresh()->quantity_available);
    }

    public function test_buyer_cannot_record_pos_sales(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create();
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);

        $this->withToken($buyer->createToken('mobile')->plainTextToken)
            ->postJson('/api/farmer/sales', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    private function farmer(array $attributes = []): User
    {
        $farmer = User::factory()->create($attributes);
        $farmer->assignRole(Role::FarmerSeller);

        return $farmer;
    }
}
