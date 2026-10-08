<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_reports_the_flag_and_blocks_normal_routes_until_the_password_changes(): void
    {
        $seller = User::factory()->create(['email' => 'flagged.seller@example.com']);
        $seller->syncRoles(Role::FarmerSeller);
        $plain = $seller->issueTemporaryPassword();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'flagged.seller@example.com',
            'password' => $plain,
        ])->assertOk()
            ->assertJsonPath('data.must_change_password', true);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/notifications')
            ->assertForbidden()
            ->assertExactJson([
                'message' => EnsurePasswordChanged::MESSAGE,
                'code' => EnsurePasswordChanged::CODE,
            ]);

        $this->withToken($token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);

        $this->withToken($token)
            ->postJson('/api/auth/password', [
                'current_password' => $plain,
                'password' => $plain,
                'password_confirmation' => $plain,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $seller->refresh();
        $this->assertTrue($seller->must_change_password);

        $this->withToken($token)
            ->postJson('/api/auth/password', [
                'current_password' => $plain,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk();

        $seller->refresh();
        $this->assertFalse($seller->must_change_password);
        $this->assertNull($seller->temporary_password_expires_at);
        $this->assertTrue(Hash::check('new-password-123', $seller->password));

        $this->withToken($token)
            ->getJson('/api/notifications')
            ->assertOk();
    }

    public function test_a_flagged_user_can_log_out(): void
    {
        $buyer = User::factory()->create();
        $buyer->syncRoles(Role::Buyer);
        $plain = $buyer->issueTemporaryPassword();

        $token = $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => $plain,
        ])->assertOk()->json('token');

        $logout = $this->withToken($token)->postJson('/api/auth/logout');

        $logout->assertOk();
        $this->assertSame(0, $buyer->tokens()->count(), (string) $logout->status());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/user')
            ->assertUnauthorized();
    }

    public function test_an_expired_temporary_password_cannot_sign_in(): void
    {
        $buyer = User::factory()->create(['email' => 'expired.buyer@example.com']);
        $buyer->syncRoles(Role::Buyer);
        $buyer->forceFill([
            'must_change_password' => true,
            'temporary_password_expires_at' => now()->subDay(),
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'expired.buyer@example.com',
            'password' => 'not-the-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));

        $this->postJson('/api/auth/login', [
            'email' => 'expired.buyer@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', User::EXPIRED_TEMPORARY_PASSWORD_MESSAGE);
    }

    public function test_api_password_reset_clears_the_flag(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['email' => 'reset.flag@example.com']);
        $buyer->syncRoles(Role::Buyer);
        $buyer->issueTemporaryPassword();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset.flag@example.com',
        ])->assertOk();

        $code = null;
        Notification::assertSentTo($buyer, ResetPasswordCodeNotification::class, function (ResetPasswordCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset.flag@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $buyer->refresh();

        $this->assertFalse($buyer->must_change_password);
        $this->assertNull($buyer->temporary_password_expires_at);
        $this->assertTrue(Hash::check('new-password-123', $buyer->password));
    }

    public function test_admin_create_returns_a_temporary_password_once_and_ignores_a_client_password(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        $response = $this->withToken($admin->createToken('mobile')->plainTextToken)
            ->postJson('/api/admin/farmer-sellers', [
                'name' => 'Juan Farmer',
                'email' => 'juan.temp@farm.ph',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone' => '09180001111',
            ])
            ->assertCreated()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonMissingPath('data.temporary_password');

        $temporaryPassword = $response->json('temporary_password');
        $seller = User::query()->where('email', 'juan.temp@farm.ph')->first();

        $this->assertIsString($temporaryPassword);
        $this->assertNotNull($seller);
        $this->assertTrue($seller->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $seller->password));
        $this->assertFalse(Hash::check('password123', $seller->password));

        $this->postJson('/api/auth/login', [
            'email' => 'juan.temp@farm.ph',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    }

    public function test_a_self_registered_buyer_is_not_flagged(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'maria.plain@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.must_change_password', false)
            ->assertJsonMissingPath('temporary_password');

        $buyer = User::query()->where('email', 'maria.plain@example.com')->first();

        $this->assertNotNull($buyer);
        $this->assertFalse($buyer->must_change_password);
        $this->assertNull($buyer->temporary_password_expires_at);
    }
}
