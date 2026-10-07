<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class OneContentEditorPerFarmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_second_content_editor_for_the_same_farm_is_rejected(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farm = Farm::factory()->create();
        $this->staff(Role::ContentEditor, $farm);

        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm($this->accountForm($farm, Role::ContentEditor, 'second.editor@example.com'))
            ->call('create')
            ->assertHasFormErrors(['farm_id'])
            ->assertSee('This farm already has a Content Editor.');

        $this->assertNull(User::query()->where('email', 'second.editor@example.com')->first());
    }

    public function test_a_content_editor_can_be_created_for_a_farm_without_one(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farm = Farm::factory()->create();

        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm($this->accountForm($farm, Role::ContentEditor, 'first.editor@example.com'))
            ->call('create')
            ->assertHasNoFormErrors();

        $editor = User::query()->where('email', 'first.editor@example.com')->first();

        $this->assertNotNull($editor);
        $this->assertSame($farm->id, $editor->farm_id);
        $this->assertTrue($editor->hasRole(Role::ContentEditor));
    }

    public function test_editing_the_existing_content_editor_on_the_same_farm_still_saves(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farm = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);

        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $editor->getKey()])
            ->fillForm([
                'name' => 'Same Farm Editor',
                'email' => $editor->email,
                'roles' => $this->roleId(Role::ContentEditor),
                'farm_id' => $farm->id,
                'status' => UserStatus::Active->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $editor->refresh();

        $this->assertSame('Same Farm Editor', $editor->name);
        $this->assertSame($farm->id, $editor->farm_id);
        $this->assertTrue($editor->hasRole(Role::ContentEditor));
    }

    public function test_changing_a_farmer_sellers_farm_is_not_blocked_by_the_content_editor(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farm = Farm::factory()->create();
        $otherFarm = Farm::factory()->create();
        $this->staff(Role::ContentEditor, $farm);
        $seller = $this->staff(Role::FarmerSeller, $otherFarm);

        $this->actingAs($admin);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $seller->getKey()])
            ->fillForm([
                'name' => $seller->name,
                'email' => $seller->email,
                'roles' => $this->roleId(Role::FarmerSeller),
                'farm_id' => $farm->id,
                'status' => UserStatus::Active->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $seller->refresh();

        $this->assertSame($farm->id, $seller->farm_id);
        $this->assertTrue($seller->hasRole(Role::FarmerSeller));
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountForm(Farm $farm, Role $role, string $email): array
    {
        return [
            'name' => 'New Account',
            'email' => $email,
            'roles' => $this->roleId($role),
            'farm_id' => $farm->id,
            'status' => UserStatus::Active->value,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ];
    }

    private function roleId(Role $role): int
    {
        return (int) RoleModel::findByName($role->value)->id;
    }
}
