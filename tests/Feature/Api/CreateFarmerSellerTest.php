<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFarmerSellerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_create_a_farmer_seller_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin);
        $token = $admin->createToken('mobile')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/admin/farmer-sellers', [
            'name' => 'Juan Farmer',
            'email' => 'juan@farm.ph',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '09180001111',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'juan@farm.ph')
            ->assertJsonPath('data.roles.0', Role::FarmerSeller->value);

        $this->assertNotNull(User::query()->where('email', 'juan@farm.ph')->value('email_verified_at'));
        $this->assertDatabaseHas('users', [
            'email' => 'juan@farm.ph',
            'status' => UserStatus::Pending->value,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'juan@farm.ph',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'Your account is awaiting approval. You can sign in after an administrator approves it.',
            );
    }

    public function test_approved_farmer_seller_can_log_in(): void
    {
        $farmer = User::factory()->create([
            'email' => 'approved.farmer@farm.ph',
            'status' => UserStatus::Active,
        ]);
        $farmer->assignRole(Role::FarmerSeller);

        $this->postJson('/api/auth/login', [
            'email' => 'approved.farmer@farm.ph',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.roles.0', Role::FarmerSeller->value)
            ->assertJsonStructure(['token']);
    }

    public function test_buyer_cannot_create_a_farmer_seller_account(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $token = $buyer->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/farmer-sellers', [
            'name' => 'Juan Farmer',
            'email' => 'juan@farm.ph',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_guest_cannot_create_a_farmer_seller_account(): void
    {
        $this->postJson('/api/admin/farmer-sellers', [
            'name' => 'Juan Farmer',
            'email' => 'juan@farm.ph',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnauthorized();
    }
}
