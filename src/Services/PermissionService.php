<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Services;

use Adhocrat\Arkhe\Contracts\PermissionRepositoryInterface;
use Adhocrat\Arkhe\Events\PermissionCreated;
use Adhocrat\Arkhe\Events\PermissionDeleted;
use Adhocrat\Arkhe\Events\PermissionUpdated;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
        private readonly EventsDispatcher $events,
        private readonly PermissionRegistrar $registrar,
    ) {
    }

    /**
     * @param  array{name: string, guard_name?: string}  $data
     */
    public function create(array $data): Permission
    {
        $permission = $this->repository->newModel();
        $permission->forceFill([
            'name'       => (string) $data['name'],
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
        if (array_key_exists('name', $data) && (string) $data['name'] !== '') {
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
        $permission->delete();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionDeleted($permission));
    }
}
