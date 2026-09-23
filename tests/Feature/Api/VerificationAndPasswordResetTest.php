<?php

namespace Tests\Feature\Api;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_can_verify_email_via_otp_code(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'maria@example.com')->first();
        $this->assertFalse($user->hasVerifiedEmail());

        $code = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return (bool) preg_match('/^\d{6}$/', $notification->code);
        });

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => $code,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_rejects_a_wrong_code(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'wrongcode@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'wrongcode@example.com')->first();

        $code = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return (bool) preg_match('/^\d{6}$/', $notification->code);
        });

        $wrongCode = $code === '000000' ? '111111' : '000000';

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => $wrongCode,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The verification code is invalid or has expired.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_rejects_an_expired_or_missing_code(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'expired@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'expired@example.com')->first();

        Cache::forget(SendEmailVerificationCodeAction::CACHE_PREFIX.$user->getKey());

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The verification code is invalid or has expired.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_on_an_already_verified_user_is_idempotent(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'already@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'already@example.com')->first();

        $code = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return (bool) preg_match('/^\d{6}$/', $notification->code);
        });

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => $code,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => '000000',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Email is already verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_a_fresh_code(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'resend@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'resend@example.com')->first();

        $firstCode = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$firstCode): bool {
            $firstCode = $notification->code;

            return (bool) preg_match('/^\d{6}$/', $notification->code);
        });

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Verification code sent.');

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 2);

        $newestCode = Notification::sent($user, VerifyEmailNotification::class)->last()->code;

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => $newestCode,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_requires_a_six_digit_code(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'digits@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => '12',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->withToken($response->json('token'))
            ->postJson('/api/auth/email/verify', [
                'code' => 'abcdef',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_unverified_buyer_can_browse_but_cannot_add_to_cart(): void
    {
        $farm = Farm::factory()->create();
        $farmer = User::factory()->create(['farm_id' => $farm->id]);
        $farmer->syncRoles(Role::FarmerSeller);
        $listing = Listing::factory()->forFarmer($farmer)->create();

        $buyer = User::factory()->unverified()->create();
        $buyer->syncRoles(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your email address is not verified.');
    }

    public function test_user_can_reset_password_with_emailed_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $user->assignRole(Role::Buyer);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'resetme@example.com',
        ])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset. Sign in with your new password.');

        $this->postJson('/api/auth/login', [
            'email' => 'resetme@example.com',
            'password' => 'new-password-123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_unknown_email_does_not_reveal_accounts_on_forgot_password(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a password reset link was sent.');
    }

    public function test_api_errors_are_json_for_not_found_and_unauthenticated(): void
    {
        $this->getJson('/api/buyer/marketplace')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJson(['message' => 'Not found.']);
    }
}
