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
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.body')
            ->assertJsonPath('data.0.excerpt', 'Mulch the beds in Cavite heat.');

        $this->asUser($farmer)
            ->getJson("/api/farmer/crop-care/{$tomato->id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Mulch the beds in Cavite heat.')
            ->assertJsonPath('data.is_official', true)
            ->assertJsonPath('data.can_edit', false);
    }

    public function test_farmer_crop_care_categories_include_counts_and_general_for_uncategorized(): void
    {
        $vegetables = Category::factory()->create(['name' => 'Vegetables', 'slug' => 'vegetables']);
        $empty = Category::factory()->create(['name' => 'Herbs', 'slug' => 'herbs']);
        CropCareArticle::factory()->create([
            'title' => 'Tomato heat',
            'category_id' => $vegetables->id,
        ]);
        CropCareArticle::factory()->create([
            'title' => 'Second tomato note',
            'category_id' => $vegetables->id,
        ]);
        CropCareArticle::factory()->uncategorized()->create([
            'title' => 'Safe handling of harvested vegetables',
        ]);
        CropCareArticle::factory()->inactive()->create([
            'title' => 'Draft vegetables',
            'category_id' => $vegetables->id,
        ]);
        CropCareArticle::factory()->inactive()->uncategorized()->create([
            'title' => 'Draft general',
        ]);

        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Vegetables')
            ->assertJsonPath('data.0.tips_count', 2)
            ->assertJsonPath('data.1.id', CropCareArticle::GENERAL_CATEGORY_ID)
            ->assertJsonPath('data.1.name', 'General')
            ->assertJsonPath('data.1.slug', 'general')
            ->assertJsonPath('data.1.tips_count', 1)
            ->assertJsonMissing(['name' => $empty->name]);

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care?category_id='.CropCareArticle::GENERAL_CATEGORY_ID)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Safe handling of harvested vegetables');
    }

    public function test_crop_care_categories_returns_401_for_guests_and_403_for_buyers(): void
    {
        $this->getJson('/api/farmer/crop-care/categories')
            ->assertUnauthorized();

        $this->asUser($this->buyer())
            ->getJson('/api/farmer/crop-care/categories')
            ->assertForbidden();
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

    public function test_farmer_can_create_own_guide_and_read_it_alongside_official_guides(): void
    {
        $category = Category::factory()->create();
        CropCareArticle::factory()->create([
            'title' => 'Official tomato note',
            'category_id' => $category->id,
        ]);
        $farmer = $this->farmer(['shop_name' => "Juan's Farm Stall"]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/crop-care', [
                'title' => 'My ampalaya trellis tip',
                'body' => 'Give sitaw a trellis before the vines tangle.',
                'category_id' => $category->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'My ampalaya trellis tip')
            ->assertJsonPath('data.is_official', false)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.author.shop_name', "Juan's Farm Stall")
            ->assertJsonPath('data.author.id', $farmer->id);

        $this->assertDatabaseHas('crop_care_articles', [
            'title' => 'My ampalaya trellis tip',
            'created_by' => $farmer->id,
        ]);

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care?category_id='.$category->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->asUser($farmer)
            ->getJson('/api/farmer/crop-care/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'My ampalaya trellis tip')
            ->assertJsonPath('data.0.body', 'Give sitaw a trellis before the vines tangle.');
    }

    public function test_farmer_can_update_and_delete_own_guide_only(): void
    {
        $category = Category::factory()->create();
        $owner = $this->farmer();
        $other = $this->farmer();
        $own = CropCareArticle::factory()->authoredBy($owner)->create([
            'title' => 'Owner guide',
            'body' => 'Original body.',
            'category_id' => $category->id,
        ]);
        $official = CropCareArticle::factory()->create([
            'title' => 'Official guide',
            'category_id' => $category->id,
        ]);
        $peer = CropCareArticle::factory()->authoredBy($other)->create([
            'title' => 'Peer guide',
            'category_id' => $category->id,
        ]);

        $this->asUser($owner)
            ->patchJson("/api/farmer/crop-care/{$own->id}", [
                'title' => 'Updated owner guide',
                'body' => 'Revised body.',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated owner guide')
            ->assertJsonPath('data.body', 'Revised body.');

        $this->asUser($owner)
            ->patchJson("/api/farmer/crop-care/{$official->id}", [
                'title' => 'Hijack official',
                'body' => 'No.',
                'category_id' => $category->id,
            ])
            ->assertForbidden();

        $this->asUser($owner)
            ->patchJson("/api/farmer/crop-care/{$peer->id}", [
                'title' => 'Hijack peer',
                'body' => 'No.',
                'category_id' => $category->id,
            ])
            ->assertForbidden();

        $this->asUser($owner)
            ->deleteJson("/api/farmer/crop-care/{$official->id}")
            ->assertForbidden();

        $this->asUser($owner)
            ->deleteJson("/api/farmer/crop-care/{$peer->id}")
            ->assertForbidden();

        $this->asUser($owner)
            ->deleteJson("/api/farmer/crop-care/{$own->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Guide deleted.');

        $this->assertDatabaseMissing('crop_care_articles', ['id' => $own->id]);
        $this->assertDatabaseHas('crop_care_articles', ['id' => $official->id]);
        $this->assertDatabaseHas('crop_care_articles', ['id' => $peer->id]);
    }

    public function test_crop_care_write_routes_return_401_and_403_for_guests_and_buyers(): void
    {
        $article = CropCareArticle::factory()->create();

        $this->postJson('/api/farmer/crop-care', [
            'title' => 'Nope',
            'body' => 'Nope',
            'category_id' => 1,
        ])->assertUnauthorized();

        $this->asUser($this->buyer())
            ->postJson('/api/farmer/crop-care', [
                'title' => 'Nope',
                'body' => 'Nope',
                'category_id' => $article->category_id,
            ])
            ->assertForbidden();

        $this->asUser($this->buyer())
            ->getJson('/api/farmer/crop-care/mine')
            ->assertForbidden();
    }

    public function test_farmer_guide_create_returns_422_without_title_body_and_category(): void
    {
        $this->asUser($this->farmer())
            ->postJson('/api/farmer/crop-care', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body', 'category_id']);
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
