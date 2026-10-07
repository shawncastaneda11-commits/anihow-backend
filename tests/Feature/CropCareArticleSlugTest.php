<?php

namespace Tests\Feature;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\Role;
use App\Filament\Resources\CropCareArticles\Pages\CreateCropCareArticle;
use App\Models\CropCareArticle;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CropCareArticleSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_duplicate_slug_in_the_same_farm_is_rejected(): void
    {
        $farm = Farm::factory()->create();
        $editor = $this->editor($farm);
        $crop = CropType::factory()->create(['farm_id' => $farm->id]);
        CropCareArticle::factory()->forFarm($farm, $editor)->create([
            'slug' => 'tomato-care',
        ]);

        $this->createArticle($editor, $crop, 'tomato-care')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertSame(1, CropCareArticle::query()->where('slug', 'tomato-care')->count());
    }

    public function test_the_same_slug_is_allowed_on_a_different_farm(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $editorA = $this->editor($farmA);
        $editorB = $this->editor($farmB);
        $crop = CropType::factory()->create(['farm_id' => $farmB->id]);
        CropCareArticle::factory()->forFarm($farmA, $editorA)->create([
            'slug' => 'tomato-care',
        ]);

        $this->createArticle($editorB, $crop, 'tomato-care')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('crop_care_articles', [
            'farm_id' => $farmB->id,
            'slug' => 'tomato-care',
        ]);
    }

    private function editor(Farm $farm): User
    {
        $user = User::factory()->create(['farm_id' => $farm->id]);
        $user->syncRoles(Role::ContentEditor);

        return $user;
    }

    private function createArticle(User $editor, CropType $crop, string $slug): Testable
    {
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::actingAs($editor)
            ->test(CreateCropCareArticle::class)
            ->fillForm([
                'title' => 'Tomato care',
                'slug' => $slug,
                'category' => ArticleCategory::CropCare->value,
                'cropTypes' => [$crop->id],
                'body' => 'Water in the morning.',
                'status' => ArticleStatus::Draft->value,
            ])
            ->call('create');
    }
}
