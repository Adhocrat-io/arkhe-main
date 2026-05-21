<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
        private readonly EventsDispatcher $events,
        private readonly ConfigRepository $config,
        private readonly PermissionRegistrar $registrar,
    ) {
    }

    /**
     * @param  array{name: string, guard_name?: string, permissions?: array<int, string>}  $data
     */
    public function create(array $data): Role
    {
        $role = $this->repository->newModel();
        $role->forceFill([
            'name'       => (string) $data['name'],
            'guard_name' => (string) ($data['guard_name'] ?? 'web'),
        ])->save();

        $this->syncPermissions($role, $data['permissions'] ?? null);

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new RoleCreated($role));

        return $role->refresh();
    }

    /**
     * @param  array{name?: string, guard_name?: string, permissions?: array<int, string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        $isCanonical = $this->isCanonical($role->name);

        // Canonical roles keep their name immutable to avoid breaking middleware
        // and config lookups; only their permissions are editable.
        if (! $isCanonical && array_key_exists('name', $data) && (string) $data['name'] !== '') {
            $role->name = (string) $data['name'];
        }

        if (! $isCanonical && array_key_exists('guard_name', $data) && (string) $data['guard_name'] !== '') {
            $role->guard_name = (string) $data['guard_name'];
        }

        $role->save();

        $this->syncPermissions($role, $data['permissions'] ?? null);

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new RoleUpdated($role));

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        if ($this->isCanonical($role->name)) {
            throw new AuthorizationException(
                "The role [{$role->name}] is canonical to Arkhe and cannot be deleted."
            );
        }

        $role->delete();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new RoleDeleted($role));
    }

    public function isCanonical(string $name): bool
    {
        return in_array($name, array_values((array) $this->config->get('arkhe.roles', [])), true);
    }

    /**
     * @param  array<int, string>|null  $permissionNames
     */
    private function syncPermissions(Role $role, ?array $permissionNames): void
    {
        if ($permissionNames === null) {
            return;
        }

        $role->syncPermissions($permissionNames);
    }
}
