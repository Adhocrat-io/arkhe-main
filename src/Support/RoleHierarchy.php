<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Encodes a configurable role hierarchy. A user can only assign roles whose
 * rank is less than or equal to their own.
 *
 * Two extension points:
 *
 * 1. Static — edit the consumer app's `config/arkhe.php`. The order of the
 *    values in the `roles` array (highest first) IS the hierarchy. Adding a
 *    new entry between two existing roles inserts it at that rank.
 *
 *        'roles' => [
 *            'root'          => 'root',
 *            'administrator' => 'administrateur',
 *            'manager'       => 'manager',   // <- new role
 *            'user'          => 'user',
 *            'guest'         => 'guest',
 *        ],
 *
 * 2. Runtime — call RoleHierarchy::register() from a ServiceProvider for
 *    role additions that come from a package or an installed module:
 *
 *        RoleHierarchy::register('manager', after: 'administrateur');
 *        RoleHierarchy::register('editor',  before: 'user');
 *        RoleHierarchy::register('intern'); // appends at the lowest rank
 */
final class RoleHierarchy
{
    /**
     * In-memory order override built by register() calls. When null, the
     * order comes straight from `config('arkhe.roles')`.
     *
     * @var array<int, string>|null
     */
    private static ?array $overrides = null;

    /**
     * Returns the ordered list of role names (highest rank first).
     *
     * @return array<int, string>
     */
    public static function order(): array
    {
        if (self::$overrides !== null) {
            return self::$overrides;
        }

        /** @var array<string, string> $configured */
        $configured = (array) config('arkhe.roles', []);

        return array_values(array_map(static fn ($v): string => (string) $v, $configured));
    }

    /**
     * @return array<string, int>
     */
    public static function ranks(): array
    {
        $order   = self::order();
        $highest = count($order) - 1;
        $map     = [];

        foreach ($order as $index => $name) {
            $map[$name] = $highest - $index;
        }

        return $map;
    }

    /**
     * Insert a role into the hierarchy. `after` and `before` are mutually
     * exclusive; with neither, the role is appended at the lowest rank.
     * If the role already exists in the hierarchy, it is repositioned.
     *
     * @throws InvalidArgumentException when both `after` and `before` are provided
     */
    public static function register(string $role, ?string $after = null, ?string $before = null): void
    {
        if ($after !== null && $before !== null) {
            throw new InvalidArgumentException('Pass either `after` or `before`, not both.');
        }

        $order = self::order();

        // Strip an existing entry so register() can also reposition.
        $order = array_values(array_filter($order, static fn (string $r): bool => $r !== $role));

        if ($after !== null) {
            $idx    = array_search($after, $order, true);
            $insert = $idx === false ? count($order) : $idx + 1;
        } elseif ($before !== null) {
            $idx    = array_search($before, $order, true);
            $insert = $idx === false ? count($order) : $idx;
        } else {
            $insert = count($order);
        }

        array_splice($order, $insert, 0, [$role]);
        self::$overrides = $order;
    }

    /**
     * Forget runtime overrides and fall back to the config-defined order.
     * Mainly useful for tests.
     */
    public static function reset(): void
    {
        self::$overrides = null;
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
            return true;
        }

        return self::rankOf($roleName) <= self::highestRankOf($actor);
    }

    /**
     * Whether an actor is allowed to act on (delete/manage) a target user.
     * True when the target's highest rank is less than or equal to the
     * actor's highest rank. False if the actor is null or outranked.
     */
    public static function canManage(?Model $actor, ?Model $target): bool
    {
        if ($actor === null || $target === null) {
            return false;
        }

        return self::highestRankOf($target) <= self::highestRankOf($actor);
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
