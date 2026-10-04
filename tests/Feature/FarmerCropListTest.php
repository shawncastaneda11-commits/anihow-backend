<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\CropTypes\CropTypeResource;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerCropListTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_farmer_with_a_crop_list_can_list_only_those_crops(): void
    {
        $farmer = $this->farmer();
        $allowed = $this->cropType(['name' => 'Kamatis']);
        $blocked = $this->cropType(['name' => 'Kamote']);
        $farmer->farmerCropTypes()->create(['crop_type_id' => $allowed->id]);

        $this->asUser($farmer)
            ->getJson('/api/crop-types')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowed->id);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Sweet potato',
                'crop_type_id' => $blocked->id,
                'unit' => 'kg',
                'price_per_unit' => 30,
                'quantity_available' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['crop_type_id']);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Ripe tomatoes',
                'crop_type_id' => $allowed->id,
                'unit' => 'kg',
                'price_per_unit' => 30,
                'quantity_available' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.crop_type.id', $allowed->id);
    }

    public function test_a_farm_lists_only_its_own_sellers_crop_types(): void
    {
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $seller = $this->farmer([], $farm);
        $outsider = $this->farmer([], $otherFarm);
        $cropType = $this->cropType();

        $row = $seller->farmerCropTypes()->create(['crop_type_id' => $cropType->id]);
        $outsider->farmerCropTypes()->create(['crop_type_id' => $cropType->id]);

        $this->assertTrue($farm->sellerCropTypes->contains(fn ($assigned) => $assigned->is($row)));
        $this->assertCount(1, $farm->sellerCropTypes);
    }

    public function test_a_buyer_still_sees_every_active_crop_type(): void
    {
        $this->cropType(['name' => 'Kamatis']);
        $this->cropType(['name' => 'Kamote']);

        $this->asUser($this->buyer())
            ->getJson('/api/crop-types')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_each_farm_keeps_its_own_crop_types(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $sellerA = $this->farmer([], $farmA);
        $sellerB = $this->farmer([], $farmB);
        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $own = $this->cropType(['farm_id' => $farmA->id, 'name' => 'Kamatis']);
        $other = $this->cropType(['farm_id' => $farmB->id, 'name' => 'Sitaw']);
        $shared = $this->cropType(['name' => 'Shared crop']);

        $this->actingAs($editorA);

        $this->assertTrue(CropTypeResource::canAccess());

        $visible = CropTypeResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visible->contains($own->id));
        $this->assertFalse($visible->contains($other->id));
        $this->assertFalse($visible->contains($shared->id));
        $this->assertTrue(Gate::forUser($editorA)->allows('update', $own));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $other));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $shared));

        $this->asUser($sellerA)
            ->getJson('/api/crop-types')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->asUser($sellerB)
            ->getJson('/api/crop-types')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $other->id);

        $this->asUser($sellerA)
            ->postJson('/api/farmer/listings', [
                'title' => 'String beans',
                'crop_type_id' => $other->id,
                'unit' => 'kg',
                'price_per_unit' => 30,
                'quantity_available' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['crop_type_id']);
    }

    public function test_a_super_admin_sees_every_farms_crop_types(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $admin = $this->staff(Role::SuperAdmin);
        $cropA = $this->cropType(['farm_id' => $farmA->id]);
        $cropB = $this->cropType(['farm_id' => $farmB->id]);

        $this->actingAs($admin);

        $visible = CropTypeResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visible->contains($cropA->id));
        $this->assertTrue($visible->contains($cropB->id));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $cropA));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $cropB));
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
