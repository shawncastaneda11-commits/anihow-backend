<?php

namespace Tests\Feature\Api;

use App\Enums\ArticleCategory;
use App\Models\CropCareArticle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CropCareApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_list_and_show_return_the_live_crop_care_shape(): void
    {
        $farm = $this->farm();
        $editor = $this->farmer(['name' => 'Farm Editor'], $farm);
        $cropType = $this->cropType();
        $article = CropCareArticle::factory()->forFarm($farm, $editor)->create([
            'title' => 'Keeping tomato plants productive',
            'body' => "Mulch the beds in Cavite heat.\n\nWater early.",
            'category' => ArticleCategory::CropCare,
        ]);
        $article->cropTypes()->attach($cropType->id);
        CropCareArticle::factory()->forFarm($farm, $editor)->draft()->create([
            'title' => 'Hidden draft',
        ]);

        $farmer = $this->farmer();

        $list = $this->asUser($farmer)->getJson('/api/crop-care')->assertOk();
        $list->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Keeping tomato plants productive')
            ->assertJsonPath('data.0.category', ArticleCategory::CropCare->value)
            ->assertJsonPath('data.0.category_label', ArticleCategory::CropCare->label())
            ->assertJsonPath('data.0.author_name', 'Farm Editor')
            ->assertJsonPath('data.0.farm.id', $farm->id);

        $this->assertNotEmpty($list->json('data.0.summary'));
        $this->assertArrayHasKey('body', $list->json('data.0'));

        $this->asUser($farmer)
            ->getJson("/api/crop-care/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Keeping tomato plants productive')
            ->assertJsonPath('data.body', "Mulch the beds in Cavite heat.\n\nWater early.")
            ->assertJsonPath('data.category', ArticleCategory::CropCare->value)
            ->assertJsonPath('data.author_name', 'Farm Editor')
            ->assertJsonPath('data.farm.id', $farm->id)
            ->assertJsonPath('data.crop_types.0.id', $cropType->id);
    }

    public function test_category_filter_returns_the_matching_subset(): void
    {
        $farm = $this->farm();
        $editor = $this->farmer([], $farm);
        $care = CropCareArticle::factory()->forFarm($farm, $editor)->create([
            'title' => 'Mulching kamatis',
            'category' => ArticleCategory::CropCare,
        ]);
        CropCareArticle::factory()->forFarm($farm, $editor)->pestManagement()->create([
            'title' => 'Fruit fly watch',
        ]);

        $farmer = $this->farmer();

        $filtered = $this->asUser($farmer)
            ->getJson('/api/crop-care?category='.ArticleCategory::CropCare->value)
            ->assertOk();

        $filtered->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $care->id)
            ->assertJsonPath('data.0.title', 'Mulching kamatis');
    }

    public function test_crop_care_index_requires_authentication(): void
    {
        $this->getJson('/api/crop-care')->assertUnauthorized();
    }
}
