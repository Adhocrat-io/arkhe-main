<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Database\Seeders;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ArkheRolesSeeder extends Seeder
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    public function run(): void
    {
        $roles = $this->config->get('arkhe.roles', []);

        foreach ($roles as $name) {
            Role::query()->firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);
        }

        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
