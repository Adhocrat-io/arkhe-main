<?php

declare(strict_types=1);

namespace Arkhe\Main\Database\Seeders;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds Arkhe's roles AND permissions, then wires the two together using
 * `arkhe.role_permissions`. Idempotent: re-running it on an existing install
 * adds any new entries declared in config without touching what's already
 * granted.
 */
class ArkheRolesSeeder extends Seeder
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function run(): void
    {
        $this->assertV3ConfigLayout();

        $guard = 'web';

        $this->seedPermissions($guard);
        $this->seedRoles($guard);
        $this->grantRolePermissions($guard);

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * A V2-shaped config (`roles` mapping role => permissions, `permissions`
     * grouped in named lists) would silently feed arrays where the seeder
     * expects names and crash mid-insert with an opaque SQL error — fail
     * loudly with the fix instead.
     */
    private function assertV3ConfigLayout(): void
    {
        $roles = (array) $this->config->get('arkhe.roles', []);
        $permissions = (array) $this->config->get('arkhe.permissions', []);

        if (array_filter($roles, 'is_array') !== [] || array_filter($permissions, 'is_array') !== []) {
            throw new RuntimeException(
                'config/arkhe.php still uses the V2 roles/permissions layout — run `php artisan arkhe:main:upgrade-from-v2` first.',
            );
        }
    }

    private function seedPermissions(string $guard): void
    {
        /** @var array<int, string> $permissions */
        $permissions = (array) $this->config->get('arkhe.permissions', []);

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }
    }

    private function seedRoles(string $guard): void
    {
        /** @var array<string, string> $roles */
        $roles = (array) $this->config->get('arkhe.roles', []);

        foreach ($roles as $name) {
            Role::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }
    }

    private function grantRolePermissions(string $guard): void
    {
        /** @var array<string, string> $roles */
        $roles = (array) $this->config->get('arkhe.roles', []);
        /** @var array<string, array<int, string>> $mapping */
        $mapping = (array) $this->config->get('arkhe.role_permissions', []);
        /** @var array<int, string> $allPermissions */
        $allPermissions = (array) $this->config->get('arkhe.permissions', []);

        foreach ($mapping as $roleKey => $perms) {
            $roleName = $roles[$roleKey] ?? null;
            if ($roleName === null) {
                continue;
            }

            $role = Role::query()->where('guard_name', $guard)->where('name', $roleName)->first();
            if ($role === null) {
                continue;
            }

            $resolved = in_array('*', $perms, true) ? $allPermissions : $perms;

            // syncPermissions is destructive — we only want to *add* the
            // package's defaults without overwriting whatever the consumer
            // app has granted to the same role.
            foreach ($resolved as $perm) {
                if (! $role->hasPermissionTo($perm, $guard)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }
}
