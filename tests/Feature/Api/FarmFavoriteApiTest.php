<?php

namespace Tests\Feature\Api;

use App\Models\Farm;
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
