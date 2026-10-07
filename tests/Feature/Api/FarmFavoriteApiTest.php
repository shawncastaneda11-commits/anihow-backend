<?php

namespace Tests\Feature\Api;

use App\Models\Farm;
use App\Models\FarmFavorite;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmFavoriteApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_buyer_can_save_list_and_remove_a_farm(): void
    {
        $farm = Farm::factory()->create([
            'name' => 'Smoke Test Farm',
            'barangay' => 'Manggahan',
            'municipality' => 'General Trias',
        ]);
        $this->farmer(['farm_id' => $farm->id], $farm);
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/buyer/farm-favorites', ['farm_id' => $farm->id])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Smoke Test Farm')
            ->assertJsonPath('data.place', 'Manggahan, General Trias')
            ->assertJsonPath('data.sellers_count', 1);

        $this->asUser($buyer)
            ->getJson('/api/buyer/farm-favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.farm_id', $farm->id);

        $this->asUser($buyer)
            ->deleteJson("/api/buyer/farm-favorites/{$farm->id}")
            ->assertOk();

        $this->asUser($buyer)
            ->getJson('/api/buyer/farm-favorites')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_farm_payloads_include_favorite_state_for_a_buyer(): void
    {
        $saved = Farm::factory()->create(['name' => 'Saved Farm']);
        $this->farmer(['shop_name' => 'Saved Stall'], $saved);
        $other = Farm::factory()->create(['name' => 'Other Farm']);
        $this->farmer(['shop_name' => 'Other Stall'], $other);
        $buyer = $this->buyer();
        FarmFavorite::factory()->create([
            'buyer_id' => $buyer->id,
            'farm_id' => $saved->id,
        ]);

        $this->asUser($buyer)
            ->getJson('/api/farms/'.$saved->id)
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true)
            ->assertJsonPath('data.favorites_count', 1);

        $this->asUser($buyer)
            ->getJson('/api/farms/'.$other->id)
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false)
            ->assertJsonPath('data.favorites_count', 0);

        $shops = $this->asUser($buyer)
            ->getJson('/api/buyer/shops')
            ->assertOk()
            ->json('data');

        $byFarm = collect($shops)->keyBy('farm.id');
        $this->assertTrue($byFarm[$saved->id]['farm']['is_favorited']);
        $this->assertSame(1, $byFarm[$saved->id]['farm']['favorites_count']);
        $this->assertFalse($byFarm[$other->id]['farm']['is_favorited']);
        $this->assertSame(0, $byFarm[$other->id]['farm']['favorites_count']);
    }

    public function test_an_inactive_farm_cannot_be_saved(): void
    {
        $farm = Farm::factory()->create(['is_active' => false]);
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/buyer/farm-favorites', ['farm_id' => $farm->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('farm_id');
    }

    public function test_saving_the_same_farm_twice_is_rejected(): void
    {
        $farm = Farm::factory()->create();
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/buyer/farm-favorites', ['farm_id' => $farm->id])
            ->assertCreated();

        $this->asUser($buyer)
            ->postJson('/api/buyer/farm-favorites', ['farm_id' => $farm->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('farm_id');
    }
}
