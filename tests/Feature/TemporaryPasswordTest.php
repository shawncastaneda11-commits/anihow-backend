<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Auth\Login;
use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->assertFormFieldHidden('password')
            ->assertFormFieldHidden('password_confirmation')
            ->fillForm($this->accountForm($farm, Role::ContentEditor, 'editor.temp@example.com'))
            ->call('create')
            ->assertHasNoFormErrors();

        $editor = User::query()->where('email', 'editor.temp@example.com')->first();
        $plain = $this->temporaryPasswordFromSession();

        $this->assertNotNull($editor);
        $this->assertTrue($editor->must_change_password);
        $this->assertNotNull($editor->temporary_password_expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->getTimestamp(),
            $editor->temporary_password_expires_at->getTimestamp(),
            60,
        );
        $this->assertNotNull($plain);
        $this->assertTrue(Hash::check($plain, $editor->password));
        $this->assertFalse(Hash::check('new-password-1', $editor->password));
        $this->assertTrue($this->notificationBodyContains(
            $editor->email,
            'Give this to the user in person. It expires in 7 days and must be changed at first sign-in.',
        ));
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

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $editor->getKey()])
            ->assertActionVisible('resetTemporaryPassword')
            ->assertFormFieldHidden('password')
            ->callAction('resetTemporaryPassword');

        $editor->refresh();
        $plain = $this->temporaryPasswordFromSession();

        $this->assertTrue($editor->must_change_password);
        $this->assertNotNull($plain);
        $this->assertTrue(Hash::check($plain, $editor->password));
        $this->assertSame(0, $editor->tokens()->count());

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

    private function temporaryPasswordFromSession(): ?string
    {
        $notifications = $this->sessionNotifications();

        if (! is_array($notifications)) {
            return null;
        }

        foreach ($notifications as $notification) {
            if (! is_array($notification) || ($notification['title'] ?? null) !== 'Temporary password') {
                continue;
            }

            return $this->temporaryPasswordFromText((string) ($notification['body'] ?? ''));
        }

        return null;
    }

    private function temporaryPasswordFromText(string $text): ?string
    {
        if (preg_match('/[abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}/', $text, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function notificationBodyContains(string ...$needles): bool
    {
        foreach ($this->sessionNotifications() as $notification) {
            if (! is_array($notification) || ($notification['title'] ?? null) !== 'Temporary password') {
                continue;
            }

            $body = (string) ($notification['body'] ?? '');
            $matches = true;

            foreach ($needles as $needle) {
                $matches = $matches && str_contains($body, $needle);
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
