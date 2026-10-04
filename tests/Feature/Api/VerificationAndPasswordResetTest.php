<?php

namespace Tests\Feature\Api;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Actions\Auth\SendPasswordResetCodeAction;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
            ->assertJsonPath('message', 'Verification code sent.')
            ->assertJsonPath(
                'verification_code',
                fn (mixed $code): bool => is_string($code) && (bool) preg_match('/^\d{6}$/', $code),
            );

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

    public function test_forgot_password_sends_a_code_for_an_existing_account_and_nothing_for_an_unknown_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $user->syncRoles(Role::Buyer);

        $known = $this->postJson('/api/auth/forgot-password', [
            'email' => 'resetme@example.com',
        ])->assertOk();

        $unknown = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        $this->assertSame(
            'If that email is registered, a password reset link was sent.',
            $known->json('message'),
        );
        $this->assertTrue(SendEmailVerificationCodeAction::shouldExposeCode());
        $known->assertJsonMissingPath('reset_code');
        $known->assertJsonMissingPath('verification_code');

        Notification::assertSentTo($user, ResetPasswordCodeNotification::class, function (ResetPasswordCodeNotification $notification): bool {
            $mail = $notification->toMail($notification);
            $line = "Your AniHow password reset code is {$notification->code}. It expires in 10 minutes.";

            return (bool) preg_match('/^\d{6}$/', $notification->code)
                && in_array($line, $mail->introLines, true);
        });
        Notification::assertCount(1);
    }

    public function test_correct_reset_code_changes_the_password_and_revokes_old_tokens(): void
    {
        Notification::fake();
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $user->syncRoles(Role::Buyer);
        $previousRememberToken = $user->remember_token;
        $oldToken = $user->createToken('mobile')->plainTextToken;

        $code = $this->requestResetCode($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'resetme@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset. Sign in with your new password.');

        $this->assertNotSame($previousRememberToken, $user->fresh()->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull(Cache::get(SendPasswordResetCodeAction::CACHE_PREFIX.$user->getKey()));
        Event::assertDispatched(PasswordReset::class);

        $this->withToken($oldToken)
            ->getJson('/api/auth/user')
            ->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'resetme@example.com',
            'password' => 'new-password-123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_wrong_reset_code_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'wrongcode@example.com']);
        $user->syncRoles(Role::Buyer);
        $code = $this->requestResetCode($user);
        $wrongCode = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/auth/reset-password', [
            'email' => 'wrongcode@example.com',
            'code' => $wrongCode,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The reset code is invalid or has expired.');

        $this->postJson('/api/auth/login', [
            'email' => 'wrongcode@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_expired_reset_code_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'expiredcode@example.com']);
        $user->syncRoles(Role::Buyer);
        $code = $this->requestResetCode($user);

        $this->travel(11)->minutes();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'expiredcode@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The reset code is invalid or has expired.');
    }

    public function test_sixth_reset_attempt_fails_after_five_wrong_codes(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'attempts@example.com']);
        $user->syncRoles(Role::Buyer);
        $code = $this->requestResetCode($user);
        $wrongCode = $code === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < SendPasswordResetCodeAction::MAX_ATTEMPTS; $attempt++) {
            $this->postJson('/api/auth/reset-password', [
                'email' => 'attempts@example.com',
                'code' => $wrongCode,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/reset-password', [
            'email' => 'attempts@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The reset code is invalid or has expired.');

        $this->assertNull(Cache::get(SendPasswordResetCodeAction::CACHE_PREFIX.$user->getKey()));
    }

    public function test_a_used_reset_code_cannot_be_reused(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'usedcode@example.com']);
        $user->syncRoles(Role::Buyer);
        $code = $this->requestResetCode($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'usedcode@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'usedcode@example.com',
            'code' => $code,
            'password' => 'another-password-123',
            'password_confirmation' => 'another-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The reset code is invalid or has expired.');

        $this->postJson('/api/auth/login', [
            'email' => 'usedcode@example.com',
            'password' => 'new-password-123',
        ])->assertOk();
    }

    public function test_suspended_account_gets_no_reset_code(): void
    {
        Notification::fake();

        $user = User::factory()->inactive()->create(['email' => 'suspended@example.com']);
        $user->syncRoles(Role::Buyer);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'suspended@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a password reset link was sent.');

        Notification::assertNothingSent();
        $this->assertNull(Cache::get(SendPasswordResetCodeAction::CACHE_PREFIX.$user->getKey()));
    }

    public function test_a_second_reset_request_within_sixty_seconds_sends_no_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'cooldown@example.com']);
        $user->syncRoles(Role::Buyer);

        $first = $this->postJson('/api/auth/forgot-password', [
            'email' => 'cooldown@example.com',
        ])->assertOk();

        $second = $this->postJson('/api/auth/forgot-password', [
            'email' => 'cooldown@example.com',
        ])->assertOk();

        $this->assertSame($first->json(), $second->json());
        Notification::assertSentToTimes($user, ResetPasswordCodeNotification::class, 1);
    }

    public function test_ten_wrong_codes_block_the_next_guess_and_any_new_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'locked@example.com']);
        $user->syncRoles(Role::Buyer);

        $first = $this->postJson('/api/auth/forgot-password', [
            'email' => 'locked@example.com',
        ])->assertOk();

        $this->guessWrong($user, times: SendPasswordResetCodeAction::MAX_ATTEMPTS);

        Carbon::setTestNow(now()->addSeconds(SendPasswordResetCodeAction::COOLDOWN_SECONDS));

        try {
            $second = $this->postJson('/api/auth/forgot-password', [
                'email' => 'locked@example.com',
            ])->assertOk();

            $this->assertSame($first->json(), $second->json());
            Notification::assertSentToTimes($user, ResetPasswordCodeNotification::class, 2);

            $this->guessWrong($user, times: SendPasswordResetCodeAction::MAX_ATTEMPTS);

            Carbon::setTestNow(now()->addSeconds(SendPasswordResetCodeAction::COOLDOWN_SECONDS));

            $this->postJson('/api/auth/reset-password', [
                'email' => 'locked@example.com',
                'code' => '111111',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.code.0', 'The reset code is invalid or has expired.');

            $blocked = $this->postJson('/api/auth/forgot-password', [
                'email' => 'locked@example.com',
            ])->assertOk();

            $this->assertSame($first->json(), $blocked->json());
            Notification::assertSentToTimes($user, ResetPasswordCodeNotification::class, 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_a_correct_code_works_after_the_send_cooldown(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'aftercooldown@example.com']);
        $user->syncRoles(Role::Buyer);
        $code = $this->requestResetCode($user);

        Carbon::setTestNow(now()->addSeconds(SendPasswordResetCodeAction::COOLDOWN_SECONDS));

        try {
            $this->postJson('/api/auth/reset-password', [
                'email' => 'aftercooldown@example.com',
                'code' => $code,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
                ->assertOk()
                ->assertJsonPath('message', 'Password reset. Sign in with your new password.');
        } finally {
            Carbon::setTestNow();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'aftercooldown@example.com',
            'password' => 'new-password-123',
        ])->assertOk();
    }

    public function test_a_successful_reset_clears_the_account_counters(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'cleared@example.com']);
        $user->syncRoles(Role::Buyer);
        $code = $this->requestResetCode($user);
        $wrongCode = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/auth/reset-password', [
            'email' => 'cleared@example.com',
            'code' => $wrongCode,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertNotNull(Cache::get(SendPasswordResetCodeAction::FAILURES_PREFIX.$user->getKey()));

        $this->postJson('/api/auth/reset-password', [
            'email' => 'cleared@example.com',
            'code' => $code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertNull(Cache::get(SendPasswordResetCodeAction::CACHE_PREFIX.$user->getKey()));
        $this->assertNull(Cache::get(SendPasswordResetCodeAction::ATTEMPTS_PREFIX.$user->getKey()));
        $this->assertNull(Cache::get(SendPasswordResetCodeAction::FAILURES_PREFIX.$user->getKey()));
        $this->assertNull(Cache::get(SendPasswordResetCodeAction::COOLDOWN_PREFIX.$user->getKey()));

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'cleared@example.com',
        ])->assertOk();

        Notification::assertSentToTimes($user, ResetPasswordCodeNotification::class, 2);
    }

    public function test_production_with_log_mailer_never_includes_verification_code(): void
    {
        $this->becomeEnvironment('production');
        config(['mail.default' => 'log']);
        Event::fake([MessageLogged::class]);
        Notification::fake();

        $register = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'prod.log@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertStatus(503)
            ->assertJsonPath('message', SendEmailVerificationCodeAction::unavailableMessage())
            ->assertJsonMissingPath('verification_code');

        $this->assertDatabaseHas('users', ['email' => 'prod.log@example.com']);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
            return $event->level === 'error'
                && str_contains($event->message, 'outbound mail is not configured');
        });

        $user = User::query()->where('email', 'prod.log@example.com')->first();
        $this->assertNotNull($user);
        $user->syncRoles(Role::Buyer);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/email/verification-notification')
            ->assertStatus(503)
            ->assertJsonPath('message', SendEmailVerificationCodeAction::unavailableMessage())
            ->assertJsonMissingPath('verification_code');
    }

    public function test_local_with_log_mailer_includes_verification_code(): void
    {
        $this->becomeEnvironment('local');
        config(['mail.default' => 'log']);
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'local.log@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonStructure(['verification_code']);

        $user = User::query()->where('email', 'local.log@example.com')->first();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonStructure(['verification_code']);
    }

    public function test_smtp_configured_never_includes_verification_code(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => 'anihow@example.com',
            'mail.mailers.smtp.password' => 'secret',
        ]);
        Notification::fake();

        $this->assertFalse(SendEmailVerificationCodeAction::shouldExposeCode());

        $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'smtp@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonMissingPath('verification_code');

        $user = User::query()->where('email', 'smtp@example.com')->first();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonMissingPath('verification_code');
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

    private function becomeEnvironment(string $environment): void
    {
        app()->detectEnvironment(fn (): string => $environment);
        config(['app.env' => $environment]);
    }

    private function requestResetCode(User $user): string
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $code = null;
        Notification::assertSentTo($user, ResetPasswordCodeNotification::class, function (ResetPasswordCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return (bool) preg_match('/^\d{6}$/', $notification->code);
        });

        return $code;
    }

    private function guessWrong(User $user, int $times): void
    {
        $code = Notification::sent($user, ResetPasswordCodeNotification::class)->last()->code;
        $wrongCode = $code === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < $times; $attempt++) {
            $this->postJson('/api/auth/reset-password', [
                'email' => $user->email,
                'code' => $wrongCode,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.code.0', 'The reset code is invalid or has expired.');
        }
    }
}
