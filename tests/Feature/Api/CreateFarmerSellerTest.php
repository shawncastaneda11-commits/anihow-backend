<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class CreateFarmerSellerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_farmer_seller_account_the_super_admin_creates_can_sign_in_at_once(): void
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
            ->assertJsonPath('data.roles.0', Role::FarmerSeller->value)
            ->assertJsonPath('data.must_change_password', true);

        $temporaryPassword = $response->json('temporary_password');

        $this->assertIsString($temporaryPassword);
        $this->assertNotNull(User::query()->where('email', 'juan@farm.ph')->value('email_verified_at'));
        $this->assertDatabaseHas('users', [
            'email' => 'juan@farm.ph',
            'status' => UserStatus::Active->value,
            'must_change_password' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'juan@farm.ph',
            'password' => $temporaryPassword,
        ])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
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

    public function test_the_migration_activates_pending_accounts_and_drops_the_approval_permission(): void
    {
        $waiting = User::factory()->create();
        DB::table('users')->where('id', $waiting->id)->update(['status' => 'pending']);
        $permission = Permission::findOrCreate('approve_farmer_seller', 'web');
        SpatieRole::findByName(Role::SuperAdmin->value, 'web')->givePermissionTo($permission);

        $migration = require database_path('migrations/2026_10_22_011640_remove_pending_account_status.php');
        $migration->up();

        $this->assertSame(UserStatus::Active, $waiting->fresh()->status);
        $this->assertNull(Permission::query()->where('name', 'approve_farmer_seller')->first());
        $this->assertSame('active', User::query()->create([
            'name' => 'Default Status',
            'email' => 'default.status@farm.ph',
            'password' => 'password',
        ])->fresh()->status->value);
    }
}
