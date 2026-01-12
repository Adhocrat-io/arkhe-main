<?php

namespace Database\Seeders;

use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates all permissions from config and assigns them to roles.
     */
    public function run(): void
    {
        // First, create all permissions from config
        $allPermissions = [];
        foreach (config('arkhe.permissions') as $groupName => $permissionsList) {
            $allPermissions[] = $groupName;
            foreach ($permissionsList as $permission) {
                $allPermissions[] = $permission;
            }
        }
        $this->createPermissions($allPermissions);

        // Then, create roles and assign permissions
        $rolesConfig = config('arkhe.roles');

        foreach ($rolesConfig as $roleName => $rolePermissions) {
            $roleEnum = UserRoleEnum::tryFrom($roleName);
            $roleLabel = $roleEnum?->label() ?? ucfirst($roleName);

            $role = Role::updateOrCreate(
                ['name' => $roleName],
                ['label' => $roleLabel]
            );

            // Handle wildcard (*) - give all permissions
            if (is_array($rolePermissions) && in_array('*', $rolePermissions, true)) {
                $role->syncPermissions($allPermissions);

                continue;
            }

            // Handle specific permission groups
            $permissionsToAssign = [];
            if (is_array($rolePermissions)) {
                foreach ($rolePermissions as $permissionPattern) {
                    // Handle patterns like 'arkhe.permissions.posts.*'
                    if (str_contains($permissionPattern, '*')) {
                        $prefix = str_replace('.*', '', $permissionPattern);
                        $prefix = str_replace('arkhe.permissions.', '', $prefix);

                        // Get permissions from the matching group
                        $groupPermissions = config("arkhe.permissions.manage-{$prefix}", []);
                        $permissionsToAssign = array_merge($permissionsToAssign, $groupPermissions);
                        $permissionsToAssign[] = "manage-{$prefix}";
                    } else {
                        $permissionsToAssign[] = $permissionPattern;
                    }
                }
            }

            if (! empty($permissionsToAssign)) {
                $this->createPermissions($permissionsToAssign);
                $role->syncPermissions($permissionsToAssign);
            }
        }
    }

    /**
     * Create permissions if they don't exist.
     */
    public function createPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }
    }
}
