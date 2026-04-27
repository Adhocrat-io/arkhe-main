<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;

final class RoleResolver
{
    /**
     * System roles that cannot be deleted.
     *
     * @return list<string>
     */
    public static function protectedRoles(): array
    {
        return [
            UserRoleEnum::ROOT->value,
            UserRoleEnum::ADMIN->value,
        ];
    }

    public static function isProtected(string $roleName): bool
    {
        return in_array($roleName, self::protectedRoles(), true);
    }

    /**
     * Roles the given user is allowed to assign to others.
     *
     * System roles are resolved via UserRoleEnum::getAllowedRoles().
     * Custom roles are resolved via config('arkhe.role_hierarchy.<role>').
     * Falls back to [<role>] if nothing is configured.
     *
     * @return list<string>
     */
    public static function allowedRolesFor(User $user): array
    {
        $roleName = $user->roles->first()?->name;

        if ($roleName === null) {
            return [UserRoleEnum::GUEST->value];
        }

        $enum = UserRoleEnum::tryFrom($roleName);

        if ($enum !== null) {
            return $enum->getAllowedRoles();
        }

        /** @var list<string> $custom */
        $custom = config("arkhe.role_hierarchy.{$roleName}", [$roleName]);

        return $custom;
    }

    /**
     * Human-readable label for a role name.
     *
     * System roles use UserRoleEnum::label(). Custom roles use
     * config('arkhe.role_labels.<role>'). Falls back to a humanized
     * version of the role name (e.g. 'federation_manager' → 'Federation manager').
     */
    public static function label(string $roleName): string
    {
        $enum = UserRoleEnum::tryFrom($roleName);

        if ($enum !== null) {
            return $enum->label();
        }

        /** @var string|null $configured */
        $configured = config("arkhe.role_labels.{$roleName}");

        if ($configured !== null) {
            return $configured;
        }

        return __(ucfirst(str_replace(['_', '-'], ' ', $roleName)));
    }
}
