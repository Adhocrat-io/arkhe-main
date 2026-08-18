<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

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

    /**
     * A role's rank, or -1 when it has none.
     *
     * Careful: -1 means "outside the hierarchy", not "harmless". A role that is
     * absent from `config('arkhe.roles')` can carry any permission — it is
     * {@see canAssign()} that refuses to hand it out lightly, not this rank.
     */
    public static function rankOf(?string $roleName): int
    {
        if ($roleName === null || $roleName === '') {
            return -1;
        }

        return self::ranks()[$roleName] ?? -1;
    }

    /**
     * Is the role known to the hierarchy declared in configuration?
     */
    public static function isRanked(?string $roleName): bool
    {
        return $roleName !== null
            && $roleName !== ''
            && array_key_exists($roleName, self::ranks());
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

    /**
     * Can the actor assign this role?
     *
     * Two cases. A **ranked** role is compared by rank: you do not assign above
     * yourself. A role **outside the hierarchy** (created by the app, absent
     * from `config('arkhe.roles')`) has no rank at all — comparing it would pit
     * -1 against -1, which opens it to anyone who holds no rank either. Yet
     * such a role can carry any permission, `manage-roles` included.
     *
     * For those, we require the actor to already hold everything the role
     * grants: you do not give away what you do not have.
     */
    public static function canAssign(?Model $actor, ?string $roleName): bool
    {
        if ($roleName === null || $roleName === '') {
            return true;
        }

        if (self::isRanked($roleName)) {
            return self::rankOf($roleName) <= self::highestRankOf($actor);
        }

        return self::grantsNothingBeyond($actor, $roleName);
    }

    /**
     * Does the role grant nothing the actor does not already hold?
     *
     * Acts as the guard for roles outside the hierarchy. An actor who carries
     * the role himself passes outright: he gains nothing by passing it on.
     */
    private static function grantsNothingBeyond(?Model $actor, string $roleName): bool
    {
        if ($actor === null) {
            return false;
        }

        if (method_exists($actor, 'hasRole') && $actor->hasRole($roleName)) {
            return true;
        }

        $role = Role::query()->where('name', $roleName)->first();

        // Unknown role: the `exists:roles,name` validation rule handles it.
        if ($role === null) {
            return true;
        }

        if (! method_exists($actor, 'can')) {
            return false;
        }

        foreach ($role->permissions as $permission) {
            if (! $actor->can($permission->name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Can the actor act on (edit, delete) this user?
     *
     * Rank decides, but it says nothing about roles outside the hierarchy: a
     * target carrying only those would report rank -1 and look manageable by
     * anyone at all, even though they hand it every right. So for those roles
     * we require the actor to already hold what they grant.
     */
    public static function canManage(?Model $actor, ?Model $target): bool
    {
        if ($actor === null || $target === null) {
            return false;
        }

        if (self::highestRankOf($target) > self::highestRankOf($actor)) {
            return false;
        }

        if (! method_exists($target, 'getRoleNames')) {
            return true;
        }

        foreach ($target->getRoleNames() as $name) {
            if (! self::isRanked((string) $name) && ! self::grantsNothingBeyond($actor, (string) $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Roles the actor can assign, meant to populate a dropdown.
     *
     * Lists ranked roles only: roles outside the hierarchy are not offered up
     * front, even where {@see canAssign()} would accept them case by case. That
     * is a deliberately cautious display choice — the guard stays the authority,
     * and this list only mirrors it without ever reaching beyond.
     *
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
