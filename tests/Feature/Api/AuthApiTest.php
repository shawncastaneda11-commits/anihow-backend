<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
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
            ->assertJsonStructure(['token', 'data' => ['id', 'name', 'email', 'roles']]);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'is_active' => true,
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
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $buyer = User::factory()->inactive()->create(['email' => 'inactive@example.com']);
        $buyer->assignRole(Role::Buyer);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', $buyer->email);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
