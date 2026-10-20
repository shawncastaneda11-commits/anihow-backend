<?php

namespace Tests\Feature;

use App\Enums\AnnouncementAudience;
use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\ListingUnit;
use App\Enums\ProductCategory;
use App\Enums\Role;
use App\Filament\Resources\CropCareArticles\CropCareArticleResource;
use App\Filament\Resources\CropCareArticles\Pages\CreateCropCareArticle;
use App\Filament\Resources\CropTypes\CropTypeResource;
use App\Filament\Resources\CropTypes\Pages\CreateCropType;
use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use App\Filament\Resources\FarmAnnouncements\Pages\CreateFarmAnnouncement;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreatePagesReturnToListTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->farm = Farm::factory()->create();
        $this->editor = User::factory()->create(['farm_id' => $this->farm->id]);
        $this->editor->syncRoles(Role::ContentEditor);

        $this->actingAs($this->editor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_creating_a_crop_type_returns_to_the_crop_type_list(): void
    {
        Livewire::actingAs($this->editor)
            ->test(CreateCropType::class)
            ->fillForm([
                'name' => 'Sitaw',
                'slug' => 'sitaw',
                'label_en' => 'String beans',
                'label_fil' => 'Sitaw',
                'category' => ProductCategory::FreshProduce->value,
                'unit_of_measure' => ListingUnit::Kilogram->value,
                'floor_price' => 30,
                'max_discount' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(CropTypeResource::getUrl('index'));
    }

    public function test_creating_an_announcement_returns_to_the_announcement_list(): void
    {
        Livewire::actingAs($this->editor)
            ->test(CreateFarmAnnouncement::class)
            ->fillForm([
                'title' => 'Market day moved',
                'body' => 'Next Saturday instead.',
                'audience' => AnnouncementAudience::Public->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(FarmAnnouncementResource::getUrl('index'));
    }

    public function test_creating_a_crop_care_article_returns_to_the_article_list(): void
    {
        $crop = CropType::factory()->create(['farm_id' => $this->farm->id]);

        Livewire::actingAs($this->editor)
            ->test(CreateCropCareArticle::class)
            ->fillForm([
                'title' => 'Tomato care',
                'slug' => 'tomato-care',
                'category' => ArticleCategory::CropCare->value,
                'cropTypes' => [$crop->id],
                'body' => 'Water in the morning.',
                'status' => ArticleStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(CropCareArticleResource::getUrl('index'));
    }
}
