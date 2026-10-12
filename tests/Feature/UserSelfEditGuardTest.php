<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class UserSelfEditGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_content_editor_cannot_change_their_own_role_farm_or_status(): void
    {
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $buyerRoleId = RoleModel::findByName(Role::Buyer->value)->id;

        $this->openEditor($editor)
            ->fillForm([
                'name' => 'Edited Editor',
                'email' => $editor->email,
                'phone' => '09170001111',
                'password' => 'New-Pass#123',
                'password_confirmation' => 'New-Pass#123',
            ])
            ->set('data.farm_id', $otherFarm->id)
            ->set('data.status', UserStatus::Suspended->value)
            ->set('data.roles', $buyerRoleId)
            ->call('save')
            ->assertHasNoFormErrors();

        $editor->refresh();

        $this->assertSame('Edited Editor', $editor->name);
        $this->assertSame('09170001111', $editor->phone);
        $this->assertTrue(Hash::check('New-Pass#123', $editor->password));
        $this->assertSame($farm->id, $editor->farm_id);
        $this->assertSame(UserStatus::Active, $editor->status);
        $this->assertTrue($editor->hasRole(Role::ContentEditor));
        $this->assertFalse($editor->hasRole(Role::Buyer));
    }

    public function test_a_super_admin_can_change_role_farm_and_status(): void
    {
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $admin = $this->staff(Role::SuperAdmin);
        $editor = $this->staff(Role::ContentEditor, $farm);
        $farmerRoleId = RoleModel::findByName(Role::FarmerSeller->value)->id;

        $this->openEditor($editor, $admin)
            ->fillForm([
                'name' => $editor->name,
                'email' => $editor->email,
                'roles' => $farmerRoleId,
                'farm_id' => $otherFarm->id,
                'status' => UserStatus::Suspended->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $editor->refresh();

        $this->assertSame($otherFarm->id, $editor->farm_id);
        $this->assertSame(UserStatus::Suspended, $editor->status);
        $this->assertTrue($editor->hasRole(Role::FarmerSeller));
        $this->assertFalse($editor->hasRole(Role::ContentEditor));
    }

    public function test_a_new_password_on_your_own_account_must_meet_the_rules(): void
    {
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());
        $oldHash = $editor->password;

        $this->openEditor($editor)
            ->fillForm([
                'password' => 'weakpass1',
                'password_confirmation' => 'weakpass1',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertSame($oldHash, $editor->fresh()->password);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function openEditor(User $record, ?User $actor = null): Testable
    {
        $actor ??= $record;

        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::actingAs($actor)->test(EditUser::class, [
            'record' => $record->getKey(),
        ]);
    }
}
