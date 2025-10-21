<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use App\Models\User;
use Arkhe\Main\DataTransferObjects\RoleDto;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function create(RoleDto $roleDto): Role
    {
        $role = Role::create($roleDto->toArray());

        if (! empty($roleDto->permissions)) {
            $role->syncPermissions($roleDto->permissions);
        }

        return $role;
    }

    public function update(Role $role, RoleDto $roleDto): Role
    {
        $role->update($roleDto->toArray());

        $role->syncPermissions($roleDto->permissions);

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
            ->whereIn('name', UserRoleEnum::fromUser($user)?->getAllowedRoles() ?? [])
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

    public function delete(Role $role): void
    {
        if (auth()->check() && ! auth()->user()->can('delete-role')) {
            throw new AuthorizationException("Vous n'avez pas les permissions nécessaires pour supprimer un rôle.");
        }

        $role->delete();
    }
}
