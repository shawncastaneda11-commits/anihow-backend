<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, $guard);
        }

        foreach (RoleEnum::cases() as $role) {
            $roleModel = Role::findOrCreate($role->value, $guard);
            $roleModel->syncPermissions(
                array_map(
                    fn (PermissionEnum $permission): string => $permission->value,
                    PermissionEnum::forRole($role),
                ),
            );
        }
    }
}
