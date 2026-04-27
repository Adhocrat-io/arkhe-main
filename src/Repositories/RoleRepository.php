<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use App\Models\User;
use Arkhe\Main\DataTransferObjects\RoleDto;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Arkhe\Main\Services\RoleResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function create(RoleDto $roleDto): Role
    {
        $createdBy = Auth::user();

        $role = DB::transaction(function () use ($roleDto) {
            $role = Role::create($roleDto->toArray());

            if (! empty($roleDto->permissions)) {
                $role->syncPermissions($roleDto->permissions);
            }

            return $role;
        });

        RoleCreated::dispatch($role, $createdBy);

        return $role;
    }

    public function update(Role $role, RoleDto $roleDto): Role
    {
        $updatedBy = Auth::user();

        $role = DB::transaction(function () use ($role, $roleDto) {
            $role->update($roleDto->toArray());
            $role->syncPermissions($roleDto->permissions);

            return $role;
        });

        RoleUpdated::dispatch($role, $updatedBy);

        return $role;
    }

    public function getRoles(): Builder
    {
        return Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->orderBy('id', 'desc');
    }

    public function getRolesFor(User $user): Collection
    {
        return $this->getRoles()
            ->whereIn('name', RoleResolver::allowedRolesFor($user))
            ->get();
    }

    public function getAllRoles(): Collection
    {
        return $this->getRoles()->get();
    }

    public function getRolesPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return $this->getRoles()->paginate($perPage);
    }

    public function find(int $roleId): ?Role
    {
        return Role::find($roleId);
    }

    public function findOrFail(int $roleId): Role
    {
        return Role::findOrFail($roleId);
    }

    /**
     * Check if a role is a protected system role.
     */
    public function isProtectedRole(Role $role): bool
    {
        return RoleResolver::isProtected($role->name);
    }

    public function delete(Role $role): void
    {
        if ($this->isProtectedRole($role)) {
            throw new \RuntimeException(__('Cannot delete protected system role: :role', ['role' => $role->name]));
        }

        $deletedBy = Auth::user();

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]);
            $role->delete();
        });

        RoleDeleted::dispatch($role, $deletedBy);
    }
}
