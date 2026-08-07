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
     * Rang d'un rôle, ou -1 s'il n'en a pas.
     *
     * Attention : -1 signifie « hors hiérarchie », pas « inoffensif ». Un rôle
     * absent de `config('arkhe.roles')` peut porter n'importe quelle
     * permission — c'est {@see canAssign()} qui refuse de l'attribuer à la
     * légère, pas ce rang.
     */
    public static function rankOf(?string $roleName): int
    {
        if ($roleName === null || $roleName === '') {
            return -1;
        }

        return self::ranks()[$roleName] ?? -1;
    }

    /**
     * Le rôle est-il connu de la hiérarchie déclarée en configuration ?
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
     * L'acteur peut-il attribuer ce rôle ?
     *
     * Deux cas. Un rôle **classé** se compare par rang : on n'attribue pas
     * au-dessus de soi. Un rôle **hors hiérarchie** (créé par l'app, absent de
     * `config('arkhe.roles')`) n'a pas de rang du tout — le comparer
     * reviendrait à opposer -1 à -1, donc à l'ouvrir à quiconque n'a lui-même
     * aucun rang. Or ce rôle peut porter n'importe quelle permission, y
     * compris `manage-roles`.
     *
     * Pour ceux-là, on exige que l'acteur détienne déjà tout ce que le rôle
     * accorde : on ne donne pas ce qu'on n'a pas.
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
     * Le rôle n'accorde-t-il rien que l'acteur ne détienne déjà ?
     *
     * Sert de garde pour les rôles hors hiérarchie. Un acteur qui porte
     * lui-même le rôle passe d'office : il ne s'élève pas en le transmettant.
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

        // Rôle inexistant : la validation `exists:roles,name` s'en charge.
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
     * L'acteur peut-il agir sur (modifier, supprimer) cet utilisateur ?
     *
     * Le rang décide, mais il ne dit rien des rôles hors hiérarchie : une
     * cible qui n'en porte que de ceux-là afficherait le rang -1 et paraîtrait
     * gérable par le premier venu, quand bien même ils lui donnent tous les
     * droits. On exige donc, pour ces rôles-là, que l'acteur détienne déjà ce
     * qu'ils accordent.
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
     * Rôles que l'acteur peut attribuer, pour alimenter un menu déroulant.
     *
     * Ne liste que les rôles classés : les rôles hors hiérarchie ne sont pas
     * proposés d'office, même si {@see canAssign()} les accepterait au cas par
     * cas. C'est un choix d'affichage prudent — la garde reste la référence,
     * cette liste ne fait que la refléter sans jamais aller au-delà.
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
