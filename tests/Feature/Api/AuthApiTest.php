<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_can_self_register_and_receive_a_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '09171234567',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.roles.0', Role::Buyer->value)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'data' => ['id', 'name', 'email', 'roles'], 'verification_code']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $response->json('verification_code'));

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'status' => UserStatus::Active->value,
        ]);
        $this->assertNull(User::query()->where('email', 'maria@example.com')->value('email_verified_at'));
        Notification::assertSentTo(
            User::query()->where('email', 'maria@example.com')->first(),
            VerifyEmailNotification::class,
        );
    }

    public function test_register_always_assigns_buyer_even_if_a_role_is_submitted(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::FarmerSeller->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.roles.0', Role::Buyer->value);

        $this->assertTrue(User::query()->where('email', 'sneaky@example.com')->first()->isBuyer());
        $this->assertFalse(User::query()->where('email', 'sneaky@example.com')->first()->isFarmerSeller());
    }

    public function test_registered_buyer_can_log_in(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.roles.0', Role::Buyer->value)
            ->assertJsonPath('data.status', UserStatus::Active->value)
            ->assertJsonStructure(['token']);

        $permissions = $response->json('data.permissions');
        $this->assertContains('place_orders', $permissions);
        $this->assertNotContains('record_walk_in_sales', $permissions);
    }

    public function test_any_active_role_can_log_in(): void
    {
        $farmer = User::factory()->create(['email' => 'farmer@example.com']);
        $farmer->assignRole(Role::FarmerSeller);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'farmer@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.roles.0', Role::FarmerSeller->value)
            ->assertJsonStructure(['token']);

        $permissions = $response->json('data.permissions');
        $this->assertContains('record_walk_in_sales', $permissions);
        $this->assertNotContains('place_orders', $permissions);
    }

    public function test_pending_farmer_seller_cannot_log_in(): void
    {
        $farmer = User::factory()->pending()->create(['email' => 'pending@example.com']);
        $farmer->syncRoles(Role::FarmerSeller);

        $this->postJson('/api/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'Your account is awaiting approval. You can sign in after an administrator approves it.',
            );
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $buyer = User::factory()->inactive()->create(['email' => 'inactive@example.com']);
        $buyer->syncRoles(Role::Buyer);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'This account is suspended. Contact the AniHow administrator.',
            );
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', $buyer->email)
            ->assertJsonPath('data.farm', null);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
