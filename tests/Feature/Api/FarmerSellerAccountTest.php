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

class FarmerSellerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
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

    public function test_the_old_api_route_for_creating_seller_accounts_is_gone(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin);

        $this->withToken($admin->createToken('mobile')->plainTextToken)
            ->postJson('/api/admin/farmer-sellers', [
                'name' => 'Juan Farmer',
                'email' => 'juan@farm.ph',
            ])
            ->assertNotFound();
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
