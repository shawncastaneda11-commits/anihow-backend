<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Filament\Resources\Farms\RelationManagers\CropTypeOverridesRelationManager;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FarmPriceGuardFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_floor_below_the_system_floor_shows_an_error_under_the_field(): void
    {
        $farm = Farm::factory()->create();
        $crop = CropType::factory()->create(['name' => 'Ampalaya', 'floor_price' => 30, 'max_discount' => 12]);
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CropTypeOverridesRelationManager::class, ['ownerRecord' => $farm, 'pageClass' => EditFarm::class])
            ->callTableAction('create', data: ['crop_type_id' => $crop->id, 'floor_price' => 12, 'max_discount' => 11])
            ->assertHasTableActionErrors(['floor_price' => 'The farm floor price cannot be lower than the system floor of PHP 30.00.'])
            ->assertHasNoTableActionErrors(['max_discount']);

        $this->assertSame(0, $farm->cropTypeOverrides()->count());
    }

    public function test_a_maximum_tawad_above_the_system_maximum_shows_an_error_under_the_field(): void
    {
        $farm = Farm::factory()->create();
        $crop = CropType::factory()->create(['floor_price' => 30, 'max_discount' => 12]);
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CropTypeOverridesRelationManager::class, ['ownerRecord' => $farm, 'pageClass' => EditFarm::class])
            ->callTableAction('create', data: ['crop_type_id' => $crop->id, 'floor_price' => 35, 'max_discount' => 15])
            ->assertHasTableActionErrors(['max_discount'])
            ->assertHasNoTableActionErrors(['floor_price']);

        $this->assertSame(0, $farm->cropTypeOverrides()->count());
    }
}
