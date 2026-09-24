<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerAnalyticsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_empty_state_returns_zeros(): void
    {
        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.period', 'week')
            ->assertJsonPath('data.summary.completed_orders', 0)
            ->assertJsonPath('data.summary.units_sold', 0)
            ->assertJsonPath('data.summary.gross_sales', 0)
            ->assertJsonPath('data.summary.average_discount', 0)
            ->assertJsonPath('data.walk_in_share.walk_in_orders', 0)
            ->assertJsonPath('data.walk_in_share.app_orders', 0)
            ->assertJsonPath('data.units_per_crop_type', [])
            ->assertJsonPath('data.best_selling', []);
    }

    public function test_only_completed_orders_count_and_walk_ins_are_included(): void
    {
        $farmer = $this->farmer();
        $cropType = CropType::factory()->create(['floor_price' => 25, 'max_discount' => 20]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 40,
        ]);

        $this->placeOrder($this->buyer(), $listing, 2);

        $completed = $this->completeOrder(
            $farmer,
            $this->placeOrder($this->buyer(), $listing, 1),
            30,
        );

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 3,
                'amount_received' => 90,
            ])
            ->assertCreated();

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_orders', 2)
            ->assertJsonPath('data.summary.units_sold', 4)
            ->assertJsonPath('data.summary.gross_sales', 120)
            ->assertJsonPath('data.walk_in_share.walk_in_orders', 1)
            ->assertJsonPath('data.walk_in_share.walk_in_sales', 90)
            ->assertJsonPath('data.walk_in_share.app_orders', 1);
    }

    public function test_another_sellers_orders_are_never_included(): void
    {
        $farm = Farm::factory()->create(['name' => 'Shared farm']);
        $otherFarm = Farm::factory()->create(['name' => 'Other farm']);
        $sellerA = $this->farmer([], $farm);
        $sellerB = $this->farmer([], $farm);
        $sellerC = $this->farmer([], $otherFarm);
        $cropType = CropType::factory()->create(['floor_price' => 25, 'max_discount' => 20]);

        $listingA = $this->listingFor($sellerA, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
        ]);
        $listingB = $this->listingFor($sellerB, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 40,
            'quantity_available' => 20,
        ]);
        $listingC = $this->listingFor($sellerC, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 50,
            'quantity_available' => 20,
        ]);

        $this->completeOrder($sellerA, $this->placeOrder($this->buyer(), $listingA, 1), 30);
        $this->completeOrder($sellerB, $this->placeOrder($this->buyer(), $listingB, 2), 80);
        $this->completeOrder($sellerC, $this->placeOrder($this->buyer(), $listingC, 3), 150);

        $this->asUser($sellerA)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_orders', 1)
            ->assertJsonPath('data.summary.units_sold', 1)
            ->assertJsonPath('data.summary.gross_sales', 30);

        $this->asUser($sellerB)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_orders', 1)
            ->assertJsonPath('data.summary.units_sold', 2)
            ->assertJsonPath('data.summary.gross_sales', 80);
    }

    public function test_a_buyer_and_content_editor_cannot_read_farmer_analytics(): void
    {
        $farm = Farm::factory()->create();
        $editor = User::factory()->create(['farm_id' => $farm->id]);
        $editor->syncRoles(Role::ContentEditor);

        $this->asUser($this->buyer())
            ->getJson('/api/farmer/analytics')
            ->assertForbidden();

        $this->asUser($editor)
            ->getJson('/api/farmer/analytics')
            ->assertForbidden();
    }
}
