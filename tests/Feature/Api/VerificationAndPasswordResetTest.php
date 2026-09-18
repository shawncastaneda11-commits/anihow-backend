<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_can_verify_email_via_signed_link(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'maria@example.com')->first();
        $this->assertFalse($user->hasVerifiedEmail());

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_buyer_can_browse_but_cannot_create_a_reservation(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole(Role::FarmerSeller);
        $listing = Listing::factory()->forFarmer($farmer)->create();

        $buyer = User::factory()->unverified()->create();
        $buyer->assignRole(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/buyer/marketplace')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
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
