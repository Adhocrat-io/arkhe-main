<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
        private readonly EventsDispatcher $events,
        private readonly ConfigRepository $config,
        private readonly PermissionRegistrar $registrar,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{name: string, guard_name?: string, permissions?: array<int, string>}  $data
     */
    public function create(array $data): Role
    {
        $this->assertNameIsAvailable((string) $data['name']);

        $role = $this->repository->newModel();
        $role->forceFill([
            'name' => (string) $data['name'],
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
     * Refuses to create a role whose name opens a door on its own.
     *
     * For V2 compatibility, the backend access middleware accepts raw role
     * names listed in `admin.roles`. A name that appears there therefore buys
     * entry without holding `access-backend`: creating a role of the same name
     * and assigning it to yourself would be enough. So we reserve those names,
     * just like the canonical ones.
     *
     * @throws AuthorizationException
     */
    private function assertNameIsAvailable(string $name): void
    {
        $actor = $this->auth->guard()->user();

        if ($actor === null && app()->runningInConsole()) {
            return;
        }

        if ($actor !== null
            && method_exists($actor, 'hasRole')
            && $actor->hasRole((string) $this->config->get('arkhe.roles.root'))) {
            return;
        }

        if ($this->isCanonical($name) || $this->grantsBackendAccessByName($name)) {
            throw new AuthorizationException(
                "The role name [{$name}] is reserved by Arkhe."
            );
        }
    }

    /**
     * Does the name appear verbatim in `admin.roles`? If so, carrying it buys
     * backend access without going through a permission at all.
     */
    private function grantsBackendAccessByName(string $name): bool
    {
        $configured = (array) $this->config->get('arkhe.admin.roles', []);
        $rolesMap = (array) $this->config->get('arkhe.roles', []);

        foreach ($configured as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            // A key resolved through the map points at a canonical role, which
            // `isCanonical()` already covers. Only raw keys matter here.
            if (array_key_exists($key, $rolesMap)) {
                continue;
            }

            if ($key === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>|null  $permissionNames
     */
    private function syncPermissions(Role $role, ?array $permissionNames): void
    {
        if ($permissionNames === null) {
            return;
        }

        $this->assertCanGrant($role, $permissionNames);

        $role->syncPermissions($permissionNames);
    }

    /**
     * You only grant what you hold yourself.
     *
     * Without this guard, anyone who can edit a role can grant it any
     * permission — starting with the one that opens the roles page — and
     * become root in a single save. The `update-role` permission alone was
     * enough to take over the whole application.
     *
     * The comparison runs on the *delta*: removing a permission you do not
     * hold stays allowed (you do not rise by taking away), and re-saving a
     * role without touching it asks for nothing more.
     *
     * Calls with no actor (console, jobs) pass through: they already imply
     * server access, where the question no longer arises.
     *
     * @param  array<int, string>  $permissionNames
     *
     * @throws AuthorizationException
     */
    private function assertCanGrant(Role $role, array $permissionNames): void
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        if ($actor === null) {
            if (app()->runningInConsole()) {
                return;
            }

            throw new AuthorizationException('Cannot change role permissions without an authenticated actor.');
        }

        // An actor who already holds everything has nothing to be denied.
        if (method_exists($actor, 'hasRole') && $actor->hasRole((string) $this->config->get('arkhe.roles.root'))) {
            return;
        }

        $current = $role->exists
            ? $role->permissions->pluck('name')->all()
            : [];

        $granted = array_diff($permissionNames, $current);

        foreach ($granted as $permission) {
            if (! method_exists($actor, 'can') || ! $actor->can($permission)) {
                throw new AuthorizationException(
                    "You are not allowed to grant the permission [{$permission}]."
                );
            }
        }
    }
}
