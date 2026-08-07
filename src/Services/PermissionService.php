<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Events\PermissionCreated;
use Arkhe\Main\Events\PermissionDeleted;
use Arkhe\Main\Events\PermissionUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
        private readonly EventsDispatcher $events,
        private readonly PermissionRegistrar $registrar,
        private readonly ConfigRepository $config,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{name: string, guard_name?: string}  $data
     */
    public function create(array $data): Permission
    {
        $this->assertNameIsAvailable((string) $data['name']);

        $permission = $this->repository->newModel();
        $permission->forceFill([
            'name' => (string) $data['name'],
            'guard_name' => (string) ($data['guard_name'] ?? 'web'),
        ])->save();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionCreated($permission));

        return $permission;
    }

    /**
     * @param  array{name?: string, guard_name?: string}  $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        $this->assertCanAlter($permission);

        if (array_key_exists('name', $data) && (string) $data['name'] !== '') {
            $this->assertNameIsAvailable((string) $data['name'], $permission);
            $permission->name = (string) $data['name'];
        }

        if (array_key_exists('guard_name', $data) && (string) $data['guard_name'] !== '') {
            $permission->guard_name = (string) $data['guard_name'];
        }

        $permission->save();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionUpdated($permission));

        return $permission;
    }

    public function delete(Permission $permission): void
    {
        $this->assertCanAlter($permission);

        $permission->delete();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionDeleted($permission));
    }

    /**
     * A permission declared in `config('arkhe.permissions')` is canonical: the
     * package code references it by name — `access-backend` guards the way into
     * the backend, `manage-roles` its sensitive area. Renaming or deleting one
     * would cut those guards, for everybody, with no way back from the UI.
     */
    public function isCanonical(string $name): bool
    {
        return in_array($name, (array) $this->config->get('arkhe.permissions', []), true);
    }

    /**
     * Refuses to touch a canonical permission, or one the actor does not hold
     * himself.
     *
     * Without this guard, renaming alone was enough to take everything: the
     * pivot tables reference the id, not the name. Renaming `view-user` to
     * `manage-roles` turned every one of its holders into an administrator at
     * a stroke, without ever touching a role or an account — and therefore
     * without meeting the guards in RoleService or UserService.
     *
     * @throws AuthorizationException
     */
    private function assertCanAlter(Permission $permission): void
    {
        if ($this->isCanonical($permission->name)) {
            throw new AuthorizationException(
                "The permission [{$permission->name}] is canonical to Arkhe and cannot be renamed or deleted."
            );
        }

        $actor = $this->actor();

        if ($actor === null) {
            return; // console: the caller already has server access.
        }

        if ($this->isRoot($actor)) {
            return;
        }

        if (! method_exists($actor, 'can') || ! $actor->can($permission->name)) {
            throw new AuthorizationException(
                "You are not allowed to alter the permission [{$permission->name}]."
            );
        }
    }

    /**
     * Refuses to write a name the actor does not hold.
     *
     * This is the other half of the lock: without it, you would rename a
     * harmless permission *towards* a powerful name. The canonical name is
     * denied to everyone — two rows carrying `manage-roles` would make
     * Spatie's checks non-deterministic.
     *
     * @throws AuthorizationException
     */
    private function assertNameIsAvailable(string $name, ?Permission $current = null): void
    {
        if ($current !== null && $current->name === $name) {
            return;
        }

        if ($this->isCanonical($name)) {
            throw new AuthorizationException(
                "The permission name [{$name}] is reserved by Arkhe."
            );
        }

        $actor = $this->actor();

        if ($actor === null || $this->isRoot($actor)) {
            return;
        }

        // A name that already exists and that the actor does not hold would be
        // a takeover by collision.
        $exists = Permission::query()->where('name', $name)->exists();

        if ($exists && (! method_exists($actor, 'can') || ! $actor->can($name))) {
            throw new AuthorizationException(
                "You are not allowed to take over the permission [{$name}]."
            );
        }
    }

    /**
     * The current actor, or null outside an HTTP context (console, jobs).
     */
    private function actor(): ?Model
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        if ($actor === null && ! app()->runningInConsole()) {
            throw new AuthorizationException('Cannot change permissions without an authenticated actor.');
        }

        return $actor;
    }

    private function isRoot(Model $actor): bool
    {
        return method_exists($actor, 'hasRole')
            && $actor->hasRole((string) $this->config->get('arkhe.roles.root'));
    }
}
