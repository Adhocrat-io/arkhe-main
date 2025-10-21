<?php

declare(strict_types=1);

namespace Arkhe\Main\Policies;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $authUser, User $user): bool
    {
        if (! $authUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if (! $user->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        return true;
    }
}
