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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value, 'web');

            // syncPermissions, not givePermissionTo. Re-running the seeder
            // after a permission is revoked must actually revoke it.
            $role->syncPermissions(
                array_map(
                    fn (PermissionEnum $permission): string => $permission->value,
                    PermissionEnum::forRole($roleEnum),
                )
            );
        }

        // Remove permissions dropped from the enum so a stale row cannot keep
        // granting access after a rename. Covers record_pos_sales and the rest
        // of the retired set.
        Permission::query()
            ->whereNotIn('name', array_column(PermissionEnum::cases(), 'value'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
