<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate(
            PermissionEnum::ManageOrganicCertification->value,
            'web',
        );

        $role = Role::query()
            ->where('name', RoleEnum::SuperAdmin->value)
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', PermissionEnum::ManageOrganicCertification->value)
            ->where('guard_name', 'web')
            ->first();

        if ($permission !== null) {
            $permission->roles()->detach();
            $permission->users()->detach();
            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
