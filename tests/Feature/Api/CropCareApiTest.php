<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Category;
use App\Models\CropCareArticle;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CropCareApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_farmer_can_browse_search_filter_and_view_crop_care_articles(): void
    {
        $vegetables = Category::factory()->create();
        $fruit = Category::factory()->create();
        $tomato = CropCareArticle::factory()->create([
            'title' => 'Keeping tomato plants productive',
            'body' => 'Mulch the beds in Cavite heat.',
            'category_id' => $vegetables->id,
        ]);
        CropCareArticle::factory()->create([
            'title' => 'Mango anthracnose after rain',
            'body' => 'Inspect fruit after prolonged rain.',
            'category_id' => $fruit->id,
        ]);
        CropCareArticle::factory()->inactive()->create([
            'title' => 'Hidden draft',
            'category_id' => $vegetables->id,
        ]);

        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care?search=tomato')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Keeping tomato plants productive');

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care?category_id='.$vegetables->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($farmer)
            ->getJson("/api/farmer/crop-care/{$tomato->id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Mulch the beds in Cavite heat.');
    }

    public function test_inactive_crop_care_is_hidden_and_buyers_are_forbidden(): void
    {
        $draft = CropCareArticle::factory()->inactive()->create();
        $farmer = $this->farmer();
        $buyer = $this->buyer();

        $this->asUser($farmer)
            ->getJson("/api/farmer/crop-care/{$draft->id}")
            ->assertNotFound();

        $this->asUser($buyer)
            ->getJson('/api/farmer/crop-care')
            ->assertForbidden();
    }

    private function farmer(array $attributes = []): User
    {
        $farmer = User::factory()->create($attributes);
        $farmer->assignRole(Role::FarmerSeller);

        return $farmer;
    }

    private function buyer(array $attributes = []): User
    {
        $buyer = User::factory()->create($attributes);
        $buyer->assignRole(Role::Buyer);

        return $buyer;
    }

    private function asUser(User $user): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('mobile')->plainTextToken);
    }
}
