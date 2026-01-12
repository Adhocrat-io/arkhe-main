<?php

declare(strict_types=1);

namespace Arkhe\Main\Policies;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * System roles that cannot be modified or deleted.
     */
    private const PROTECTED_ROLES = ['root', 'admin'];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRoleEnum::ROOT->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasRole(UserRoleEnum::ROOT->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRoleEnum::ROOT->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasRole(UserRoleEnum::ROOT->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        if (! $user->hasRole(UserRoleEnum::ROOT->value)) {
            return false;
        }

        // Cannot delete protected system roles
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return false;
        }

        return true;
    }
}
