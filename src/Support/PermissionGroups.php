<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

/**
 * Sorts permissions by resource, so that a role's form shows readable groups
 * rather than a flat list of several dozen checkboxes.
 *
 * Two sources, in this order:
 *
 * 1. `config('arkhe.permission_groups')` — an explicit
 *    `group => [permissions]` map, for when the app wants to decide the
 *    split and the ordering itself.
 *
 * 2. Otherwise, grouping is inferred from the naming convention documented in
 *    `config/arkhe.php`: `manage-<resource>` for the shorthand and
 *    `<verb>-<resource>` for the fine-grained actions. So `view-user`,
 *    `create-user` and `manage-users` all land under "users".
 *
 * Anything that fits no resource (a single-word permission such as
 * `access-backend`) goes to an "other" group, never dropped.
 */
final class PermissionGroups
{
    /**
     * Verbs recognised at the head of a permission. `manage` gets special
     * treatment: it names the group as much as it belongs to it.
     *
     * @var array<int, string>
     */
    private const VERBS = ['manage', 'view', 'create', 'update', 'delete', 'restore', 'force'];

    /**
     * @param  iterable<int, string>  $permissionNames
     * @return array<string, array<int, string>> group => sorted permissions
     */
    public static function build(iterable $permissionNames): array
    {
        $configured = (array) config('arkhe.permission_groups', []);

        if ($configured !== []) {
            return self::fromConfig($configured, $permissionNames);
        }

        return self::infer($permissionNames);
    }

    /**
     * Honours the order and the split defined in config, but only shows the
     * permissions that actually exist in the database — a config running ahead
     * of the seeder must not produce checkboxes with nothing behind them.
     * Whatever the config forgets is picked up at the end rather than hidden.
     *
     * @param  array<string, array<int, string>>  $configured
     * @param  iterable<int, string>  $permissionNames
     * @return array<string, array<int, string>>
     */
    private static function fromConfig(array $configured, iterable $permissionNames): array
    {
        $existing = self::normalise($permissionNames);
        $groups = [];
        $placed = [];

        foreach ($configured as $group => $children) {
            $names = array_values(array_filter(
                (array) $children,
                static fn ($name): bool => is_string($name) && in_array($name, $existing, true),
            ));

            // Under the Arkhe convention the group name is itself a permission
            // (`manage-users` heads its children), so we show it alongside.
            if (is_string($group) && in_array($group, $existing, true)) {
                array_unshift($names, $group);
            }

            if ($names === []) {
                continue;
            }

            $groups[(string) $group] = $names;
            $placed = array_merge($placed, $names);
        }

        $orphans = array_values(array_diff($existing, $placed));
        if ($orphans !== []) {
            $groups[self::otherKey()] = $orphans;
        }

        return $groups;
    }

    /**
     * @param  iterable<int, string>  $permissionNames
     * @return array<string, array<int, string>>
     */
    private static function infer(iterable $permissionNames): array
    {
        $groups = [];

        foreach (self::normalise($permissionNames) as $name) {
            $groups[self::resourceOf($name)][] = $name;
        }

        // The `manage-X` macros head their group, the rest goes alphabetically:
        // you read the shorthand first, then the details.
        foreach ($groups as $resource => $names) {
            usort($names, static function (string $a, string $b): int {
                $aManage = str_starts_with($a, 'manage-');
                $bManage = str_starts_with($b, 'manage-');

                return $aManage === $bManage ? strcmp($a, $b) : ($aManage ? -1 : 1);
            });

            $groups[$resource] = $names;
        }

        // The catch-all group closes the list, wherever it first appeared.
        $other = self::otherKey();
        if (isset($groups[$other])) {
            $orphans = $groups[$other];
            unset($groups[$other]);
            ksort($groups);
            $groups[$other] = $orphans;

            return $groups;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * The resource a permission targets: the segment following the verb,
     * pluralised so that `view-user` and `manage-users` meet up.
     */
    private static function resourceOf(string $permission): string
    {
        $parts = explode('-', $permission);

        if (count($parts) < 2 || ! in_array($parts[0], self::VERBS, true)) {
            return self::otherKey();
        }

        // `force-delete-user`: the verb spans two segments.
        if ($parts[0] === 'force' && count($parts) > 2) {
            array_shift($parts);
        }

        array_shift($parts);

        $resource = implode('-', $parts);

        return $resource === '' ? self::otherKey() : self::pluralise($resource);
    }

    /**
     * A resource's grouping key. The plural is only there to bring two
     * spellings of the same thing together (`view-user` and `manage-users`) —
     * not to produce correct English, hence no Str::plural. The displayed
     * label goes through {@see label()} instead.
     */
    private static function pluralise(string $resource): string
    {
        if (str_ends_with($resource, 's')) {
            return $resource;
        }

        if (str_ends_with($resource, 'y')) {
            return substr($resource, 0, -1).'ies';
        }

        return $resource.'s';
    }

    /**
     * A group's readable label. A dedicated translation wins whenever one
     * exists (`arkhe::arkhe.permissions.groups.users`), otherwise the key is
     * put back into plain wording: "site-seos" has no place in a UI.
     */
    public static function label(string $group): string
    {
        $key = 'arkhe::arkhe.permissions.groups.'.$group;

        if (($translated = __($key)) !== $key) {
            return (string) $translated;
        }

        return ucfirst(str_replace('-', ' ', $group));
    }

    private static function otherKey(): string
    {
        return 'other';
    }

    /**
     * @param  iterable<int, string>  $permissionNames
     * @return array<int, string>
     */
    private static function normalise(iterable $permissionNames): array
    {
        $names = [];

        foreach ($permissionNames as $name) {
            $name = (string) $name;
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
