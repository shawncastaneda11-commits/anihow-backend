<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Auth\Login;
use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class TemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_creating_an_account_issues_a_temporary_password_instead_of_a_typed_one(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farm = Farm::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->assertFormFieldHidden('password')
            ->assertFormFieldHidden('password_confirmation')
            ->fillForm($this->accountForm($farm, Role::ContentEditor, 'editor.temp@example.com'))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertActionMounted('temporaryPassword');

        $editor = User::query()->where('email', 'editor.temp@example.com')->first();
        $plain = $this->mountedTemporaryPassword($component);

        $this->assertNotNull($editor);
        $this->assertTrue($editor->must_change_password);
        $this->assertNotNull($editor->temporary_password_expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->getTimestamp(),
            $editor->temporary_password_expires_at->getTimestamp(),
            60,
        );
        $this->assertTrue(Hash::check($plain, $editor->password));
        $this->assertFalse(Hash::check('new-password-1', $editor->password));
        $this->assertTemporaryPasswordModal($component, $plain, $editor->email);
        $this->assertPasswordWasNotNotified($plain);
        $this->assertTrue($this->notificationTitleExists('Created'));

        $component
            ->callMountedAction()
            ->assertRedirect(UserResource::getUrl('view', ['record' => $editor]));

        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $editor->email,
                'password' => $plain,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($editor);
    }

    public function test_reset_temporary_password_is_hidden_for_self_and_super_admins_and_revokes_tokens(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $otherAdmin = $this->staff(Role::SuperAdmin);
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());
        $token = $editor->createToken('mobile')->plainTextToken;

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden('resetTemporaryPassword')
            ->assertFormFieldVisible('password');

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $otherAdmin->getKey()])
            ->assertActionHidden('resetTemporaryPassword')
            ->assertFormFieldHidden('password');

        $component = Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $editor->getKey()])
            ->assertActionVisible('resetTemporaryPassword')
            ->assertFormFieldHidden('password')
            ->callAction('resetTemporaryPassword')
            ->assertActionMounted('temporaryPassword');

        $editor->refresh();
        $plain = $this->mountedTemporaryPassword($component);

        $this->assertTrue($editor->must_change_password);
        $this->assertTrue(Hash::check($plain, $editor->password));
        $this->assertTemporaryPasswordModal($component, $plain, $editor->email);
        $this->assertPasswordWasNotNotified($plain);
        $this->assertSame(0, $editor->tokens()->count());

        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $editor->email,
                'password' => $plain,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($editor);
        $this->app['auth']->forgetGuards();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_a_flagged_panel_user_must_change_password_before_using_the_cms(): void
    {
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());
        $plain = $editor->issueTemporaryPassword();

        $this->actingAs($editor)
            ->get('/admin')
            ->assertRedirect(ChangePassword::getUrl());

        $this->actingAs($editor)
            ->get(ChangePassword::getUrl())
            ->assertOk()
            ->assertSee('Change your password')
            ->assertSee('Current password');

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($editor)
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $editor->refresh();
        $this->assertTrue($editor->must_change_password);

        $this->actingAs($editor);

        Livewire::actingAs($editor)
            ->test(ChangePassword::class)
            ->fillForm([
                'current_password' => $plain,
                'password' => $plain,
                'password_confirmation' => $plain,
            ], 'form')
            ->call('save')
            ->assertHasFormErrors(['password']);

        $editor->refresh();
        $this->assertTrue($editor->must_change_password);

        Livewire::actingAs($editor)
            ->test(ChangePassword::class)
            ->fillForm([
                'current_password' => $plain,
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ], 'form')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getLoginUrl());

        $editor->refresh();

        $this->assertFalse($editor->must_change_password);
        $this->assertNull($editor->temporary_password_expires_at);
        $this->assertTrue(Hash::check('new-password-1', $editor->password));
        $this->assertGuest();
    }

    public function test_an_expired_temporary_password_cannot_sign_in_to_the_cms(): void
    {
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());
        $editor->forceFill([
            'must_change_password' => true,
            'temporary_password_expires_at' => now()->subDay(),
        ])->save();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $editor->email,
                'password' => 'not-the-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertDontSee(User::EXPIRED_TEMPORARY_PASSWORD_MESSAGE);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $editor->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertSee(User::EXPIRED_TEMPORARY_PASSWORD_MESSAGE);

        $this->assertGuest();
    }

    public function test_changing_your_own_password_in_the_cms_clears_the_flag(): void
    {
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());
        $editor->forceFill([
            'must_change_password' => true,
            'temporary_password_expires_at' => now()->addDay(),
        ])->save();

        Livewire::actingAs($editor)
            ->test(EditUser::class, ['record' => $editor->getKey()])
            ->fillForm([
                'name' => $editor->name,
                'email' => $editor->email,
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $editor->refresh();

        $this->assertFalse($editor->must_change_password);
        $this->assertNull($editor->temporary_password_expires_at);
        $this->assertTrue(Hash::check('new-password-1', $editor->password));
    }

    public function test_the_super_admin_seeder_does_not_flag_the_account(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $admin = User::query()->where('email', 'admin@anihow.local')->first();

        $this->assertNotNull($admin);
        $this->assertFalse($admin->must_change_password);
        $this->assertNull($admin->temporary_password_expires_at);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create([
            'farm_id' => $farm?->id,
            'status' => UserStatus::Active,
        ]);
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
            'roles' => (int) RoleModel::findByName($role->value)->id,
            'farm_id' => $farm->id,
            'status' => UserStatus::Active->value,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sessionNotifications(): array
    {
        $notifications = array_merge(
            session('filament.notifications', []),
            session('filament.claimed_notifications', []),
        );

        return array_values(array_filter($notifications, is_array(...)));
    }

    private function mountedTemporaryPassword(Testable $component): string
    {
        $arguments = $component->instance()->mountedActions[0]['arguments'] ?? [];
        $password = $arguments['password'] ?? null;

        $this->assertIsString($password);
        $this->assertNotSame('', $password);

        return $password;
    }

    private function assertTemporaryPasswordModal(Testable $component, string $password, string $email): void
    {
        $action = $component->instance()->getMountedAction();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertFalse($action->isModalClosedByClickingAway());
        $this->assertFalse($action->hasModalCloseButton());
        $this->assertSame(Alignment::Center, $action->getModalAlignment());

        $component
            ->assertMountedActionModalSee('Temporary password')
            ->assertMountedActionModalSee($password)
            ->assertMountedActionModalSee($email)
            ->assertMountedActionModalSee(User::temporaryPasswordGuidance())
            ->assertMountedActionModalSee('Copy password')
            ->assertMountedActionModalSee('Done')
            ->assertMountedActionModalDontSee('Cancel');
    }

    private function assertPasswordWasNotNotified(string $password): void
    {
        foreach ($this->sessionNotifications() as $notification) {
            $this->assertNotSame('Temporary password', $notification['title'] ?? null);

            $encoded = json_encode($notification);

            $this->assertIsString($encoded);
            $this->assertStringNotContainsString($password, $encoded);
        }
    }

    private function notificationTitleExists(string $title): bool
    {
        foreach ($this->sessionNotifications() as $notification) {
            if (($notification['title'] ?? null) === $title) {
                return true;
            }
        }

        return false;
    }
}
