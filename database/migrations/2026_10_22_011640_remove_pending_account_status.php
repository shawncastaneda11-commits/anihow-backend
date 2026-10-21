<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Accounts no longer wait for approval: the Super Admin creates every
 * farmer-seller, so a new account is active at once. Accounts still pending
 * become active, and the approval permission goes away.
 */
return new class extends Migration
{
    private const APPROVE_PERMISSION = 'approve_farmer_seller';

    public function up(): void
    {
        DB::table('users')->where('status', 'pending')->update(['status' => 'active']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->change();
        });

        $permission = Permission::query()
            ->where('name', self::APPROVE_PERMISSION)
            ->where('guard_name', 'web')
            ->first();

        if ($permission !== null) {
            $permission->roles()->detach();
            $permission->users()->detach();
            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Restores the column default and the permission. Accounts made active by
     * up() stay active: which of them were pending is not recorded.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('pending')->change();
        });

        $permission = Permission::findOrCreate(self::APPROVE_PERMISSION, 'web');
        $role = Role::query()
            ->where('name', RoleEnum::SuperAdmin->value)
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
