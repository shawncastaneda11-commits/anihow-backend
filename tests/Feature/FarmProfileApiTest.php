<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\FarmPhoto;
use App\Models\User;
use App\Support\ListingStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmProfileApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_buyer_can_view_an_active_farm_profile(): void
    {
        $farm = Farm::factory()->create([
            'name' => 'Manggahan Farm',
            'description' => 'Morning harvest.',
            'contact_person' => 'Aling Nena',
            'contact_number' => '09171230001',
            'barangay' => 'Manggahan',
            'municipality' => 'General Trias',
            'pickup_point' => 'Barangay hall',
            'cover_photo_path' => 'farms/cover.jpg',
        ]);
        $seller = $this->farmer(['shop_name' => 'Nena Stall'], $farm);
        FarmPhoto::factory()->create([
            'farm_id' => $farm->id,
            'path' => 'farms/1/photo.jpg',
            'caption' => 'Tomatoes at dawn',
            'sort_order' => 0,
        ]);
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/farms/'.$farm->id)
            ->assertOk()
            ->assertJsonPath('data.id', $farm->id)
            ->assertJsonPath('data.name', 'Manggahan Farm')
            ->assertJsonPath('data.description', 'Morning harvest.')
            ->assertJsonPath('data.contact_person', 'Aling Nena')
            ->assertJsonPath('data.pickup_point', 'Barangay hall')
            ->assertJsonPath('data.cover_photo_url', ListingStorage::disk()->url('farms/cover.jpg'))
            ->assertJsonPath('data.photos.0.caption', 'Tomatoes at dawn')
            ->assertJsonPath('data.farmer_sellers_count', 1)
            ->assertJsonPath('data.storefronts.0.id', $seller->id)
            ->assertJsonPath('data.storefronts.0.shop_name', 'Nena Stall')
            ->assertJsonPath('data.storefronts.0.avatar', null);
    }

    public function test_a_farmer_seller_can_view_an_active_farm_profile(): void
    {
        $farm = Farm::factory()->create(['name' => 'Manggahan Farm']);
        $seller = $this->farmer([], $farm);

        $this->asUser($seller)
            ->getJson('/api/farms/'.$farm->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Manggahan Farm');
    }

    public function test_an_inactive_farm_returns_not_found(): void
    {
        $farm = Farm::factory()->inactive()->create();

        $this->asUser($this->buyer())
            ->getJson('/api/farms/'.$farm->id)
            ->assertNotFound();
    }

    public function test_unauthenticated_farm_show_returns_unauthorized(): void
    {
        $farm = Farm::factory()->create();

        $this->getJson('/api/farms/'.$farm->id)
            ->assertUnauthorized();
    }

    public function test_a_content_editor_cannot_add_photos_to_another_farm(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $editorA = User::factory()->create(['farm_id' => $farmA->id]);
        $editorA->syncRoles(Role::ContentEditor);
        $photoB = FarmPhoto::factory()->create(['farm_id' => $farmB->id]);

        $this->assertTrue(Gate::forUser($editorA)->allows('createForFarm', [FarmPhoto::class, $farmA]));
        $this->assertTrue(Gate::forUser($editorA)->denies('createForFarm', [FarmPhoto::class, $farmB]));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $photoB));
        $this->assertTrue(Gate::forUser($editorA)->denies('delete', $photoB));
    }

    public function test_shop_and_listing_resources_include_the_sellers_farm(): void
    {
        $farm = Farm::factory()->create(['name' => 'Manggahan Farm']);
        $seller = $this->farmer(['shop_name' => 'Nena Stall'], $farm);
        $listing = $this->listingFor($seller, ['is_active' => true]);
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/shops/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.farm.id', $farm->id)
            ->assertJsonPath('data.farm.name', 'Manggahan Farm');

        $this->asUser($seller)
            ->getJson('/api/farmer/shop')
            ->assertOk()
            ->assertJsonPath('data.farm.id', $farm->id)
            ->assertJsonPath('data.farm.name', 'Manggahan Farm');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.farm.id', $farm->id)
            ->assertJsonPath('data.farm.name', 'Manggahan Farm');
    }
}
