<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_change_password_with_the_current_password(): void
    {
        $buyer = $this->buyer([
            'email' => 'changeme@example.com',
            'password' => 'password123',
        ]);

        $this->asUser($buyer)
            ->postJson('/api/auth/password', [
                'current_password' => 'password123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated.');

        $this->assertTrue(Hash::check('new-password-123', $buyer->fresh()->password));

        $this->postJson('/api/auth/login', [
            'email' => 'changeme@example.com',
            'password' => 'new-password-123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_change_password_rejects_the_wrong_current_password(): void
    {
        $buyer = $this->buyer(['password' => 'password123']);

        $this->asUser($buyer)
            ->postJson('/api/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password123', $buyer->fresh()->password));
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->postJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnauthorized();
    }
}
