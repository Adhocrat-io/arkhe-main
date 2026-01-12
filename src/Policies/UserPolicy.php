<?php

declare(strict_types=1);

namespace Arkhe\Main\Policies;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;

class UserPolicy
{
    /**
     * Check if the authenticated user can manage the target user.
     *
     * Rules:
     * - Only root and admin can manage users
     * - Only root can manage other root users
     * - Admins can manage all non-root users
     */
    private function canManage(User $authUser, User $targetUser): bool
    {
        // Only root and admin can manage users
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        // Only root can manage root users
        if ($targetUser->hasRole(UserRoleEnum::ROOT->value) && ! $authUser->hasRole(UserRoleEnum::ROOT->value)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $authUser, User $user): bool
    {
        return $this->canManage($authUser, $user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $authUser): bool
    {
        return $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $authUser, User $user): bool
    {
        return $this->canManage($authUser, $user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $authUser, User $user): bool
    {
        // Cannot delete yourself
        if ($authUser->id === $user->id) {
            return false;
        }

        return $this->canManage($authUser, $user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $authUser, User $user): bool
    {
        return $this->canManage($authUser, $user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $authUser, User $user): bool
    {
        // Cannot force delete yourself
        if ($authUser->id === $user->id) {
            return false;
        }

        return $this->canManage($authUser, $user);
    }
}
