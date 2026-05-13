<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Encodes the Arkhe role hierarchy: a user can only assign roles whose rank
 * is less than or equal to their own. The rank order (highest first) is:
 * root > administrateur > user > guest.
 */
final class RoleHierarchy
{
    /**
     * Role names in descending rank order, mapped to their numeric rank.
     *
     * @return array<string, int>
     */
    public static function ranks(): array
    {
        $names = [
            (string) config('arkhe.roles.root'),
            (string) config('arkhe.roles.administrator'),
            (string) config('arkhe.roles.user'),
            (string) config('arkhe.roles.guest'),
        ];

        $highest = count($names) - 1;
        $map     = [];
        foreach ($names as $index => $name) {
            $map[$name] = $highest - $index;
        }

        return $map;
    }

    public static function rankOf(?string $roleName): int
    {
        if ($roleName === null || $roleName === '') {
            return -1;
        }

        return self::ranks()[$roleName] ?? -1;
    }

    public static function highestRankOf(?Model $user): int
    {
        if ($user === null || ! method_exists($user, 'getRoleNames')) {
            return -1;
        }

        $max = -1;
        foreach ($user->getRoleNames() as $name) {
            $max = max($max, self::rankOf((string) $name));
        }

        return $max;
    }

    public static function canAssign(?Model $actor, ?string $roleName): bool
    {
        if ($roleName === null || $roleName === '') {
            return true; // clearing a role is always allowed
        }

        return self::rankOf($roleName) <= self::highestRankOf($actor);
    }

    /**
     * @return array<int, string>
     */
    public static function rolesAssignableBy(?Model $actor): array
    {
        $cap = self::highestRankOf($actor);

        return array_values(array_filter(
            array_keys(self::ranks()),
            static fn (string $name): bool => self::rankOf($name) <= $cap,
        ));
    }
}
