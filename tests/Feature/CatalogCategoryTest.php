<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Enums\ProductCategory;
use App\Enums\Role as RoleEnum;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\User;
use App\Policies\FarmPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CatalogCategoryTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_permission_migration_grants_organic_certification_only_to_super_admin(): void
    {
        $permission = Permission::findByName(PermissionEnum::ManageOrganicCertification->value, 'web');
        $superAdmin = Role::findByName(RoleEnum::SuperAdmin->value, 'web');
        $editor = Role::findByName(RoleEnum::ContentEditor->value, 'web');

        $superAdmin->revokePermissionTo($permission);
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertNull(
            Permission::query()->where('name', PermissionEnum::ManageOrganicCertification->value)->first(),
        );

        $migration = require database_path('migrations/2026_10_05_021622_grant_manage_organic_certification_permission.php');
        $migration->up();

        $superAdmin = Role::findByName(RoleEnum::SuperAdmin->value, 'web');
        $editor = Role::findByName(RoleEnum::ContentEditor->value, 'web');

        $this->assertTrue($superAdmin->hasPermissionTo(PermissionEnum::ManageOrganicCertification->value));
        $this->assertFalse($editor->hasPermissionTo(PermissionEnum::ManageOrganicCertification->value));
    }

    public function test_a_content_editor_cannot_manage_organic_certification(): void
    {
        $farm = Farm::factory()->create();
        $editor = User::factory()->create(['farm_id' => $farm->id]);
        $editor->syncRoles(RoleEnum::ContentEditor);
        $admin = User::factory()->create();
        $admin->syncRoles(RoleEnum::SuperAdmin);

        $policy = app(FarmPolicy::class);

        $this->assertFalse($policy->manageOrganicCertification($editor, $farm));
        $this->assertTrue($policy->manageOrganicCertification($admin, $farm));
        $this->assertFalse($editor->can('manageOrganicCertification', $farm));
        $this->assertTrue($admin->can('manageOrganicCertification', $farm));
    }

    public function test_a_new_crop_type_defaults_to_fresh_produce_and_buyers_can_filter(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $fresh = CropType::factory()->create(['name' => 'Pechay']);
        $processed = CropType::factory()->create([
            'name' => 'Banana chips',
            'category' => ProductCategory::ValueAdded,
        ]);

        $this->assertSame(ProductCategory::FreshProduce, $fresh->category);

        $freshListing = $this->listingFor($farmer, [
            'crop_type_id' => $fresh->id,
            'title' => 'Bunch of pechay',
        ]);
        $processedListing = $this->listingFor($farmer, [
            'crop_type_id' => $processed->id,
            'title' => 'Banana chips, small pack',
        ]);

        $filtered = $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?category=value_added')
            ->assertOk();

        $ids = collect($filtered->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($processedListing->id));
        $this->assertFalse($ids->contains($freshListing->id));
        $this->assertSame('value_added', $filtered->json('data.0.category.value'));
        $this->assertSame('Value-added', $filtered->json('data.0.category.label'));
        $this->assertSame('Prosesong Produkto', $filtered->json('data.0.category.label_fil'));

        $cropTypes = $this->asUser($buyer)
            ->getJson('/api/crop-types?category=fresh_produce')
            ->assertOk();

        $cropIds = collect($cropTypes->json('data'))->pluck('id');
        $this->assertTrue($cropIds->contains($fresh->id));
        $this->assertFalse($cropIds->contains($processed->id));
        $this->assertSame('fresh_produce', $cropTypes->json('data.0.category.value'));
    }

    public function test_certified_organic_requires_a_current_farm_certificate(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $cropType = $this->cropType(['floor_price' => 20, 'max_discount' => 5]);
        $body = [
            'title' => 'Kamatis',
            'crop_type_id' => $cropType->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'quantity_available' => 8,
        ];

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                ...$body,
                'growing_method' => 'certified_organic',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'growing_method' => 'Your farm has no valid organic certificate on file.',
            ]);

        $this->certify($farmer->farm);

        $created = $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                ...$body,
                'title' => 'Certified kamatis',
                'growing_method' => 'certified_organic',
            ])
            ->assertCreated();

        $this->assertSame('certified_organic', $created->json('data.growing_method'));
        $this->assertSame('certified', $created->json('data.organic_badge'));
        $this->assertSame('OCCP', $created->json('data.organic_certifier'));

        $farmer->farm->update(['organic_certified_until' => today()->subDay()]);

        $shown = $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$created->json('data.id'))
            ->assertOk();

        $this->assertSame('certified_organic', $shown->json('data.growing_method'));
        $this->assertNull($shown->json('data.organic_badge'));
        $this->assertNull($shown->json('data.organic_certifier'));

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?growing_method=certified_organic')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_naturally_grown_is_accepted_without_a_certificate(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $cropType = $this->cropType(['floor_price' => 20, 'max_discount' => 5]);

        $created = $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Sitaw',
                'crop_type_id' => $cropType->id,
                'unit' => 'kg',
                'price_per_unit' => 30,
                'quantity_available' => 8,
                'growing_method' => 'naturally_grown',
            ])
            ->assertCreated();

        $this->assertSame('naturally_grown', $created->json('data.organic_badge'));

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?growing_method=naturally_grown')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created->json('data.id'));
    }

    public function test_updating_to_certified_organic_is_refused_without_a_certificate(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Talong']);

        $this->asUser($farmer)
            ->patchJson('/api/farmer/listings/'.$listing->id, [
                'growing_method' => 'certified_organic',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'growing_method' => 'Your farm has no valid organic certificate on file.',
            ]);
    }

    private function certify(Farm $farm, ?Carbon $until = null): void
    {
        $farm->update([
            'organic_certifier' => 'OCCP',
            'organic_certificate_no' => 'OC-100',
            'organic_certified_until' => $until ?? today()->addYear(),
        ]);
    }
}
