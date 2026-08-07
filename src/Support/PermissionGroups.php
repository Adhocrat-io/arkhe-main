<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

/**
 * Range les permissions par ressource, pour que la fiche d'un rôle présente
 * des groupes lisibles plutôt qu'une liste de plusieurs dizaines de cases.
 *
 * Deux sources, dans cet ordre :
 *
 * 1. `config('arkhe.permission_groups')` — une map explicite
 *    `groupe => [permissions]` quand l'app veut décider elle-même du
 *    découpage et de l'ordre.
 *
 * 2. À défaut, le groupement est déduit de la convention de nommage documentée
 *    dans `config/arkhe.php` : `manage-<ressource>` pour le raccourci et
 *    `<verbe>-<ressource>` pour les actions fines. `view-user`, `create-user`
 *    et `manage-users` se retrouvent donc sous « users ».
 *
 * Ce qui n'entre dans aucune ressource (une permission d'un seul mot, par
 * exemple `access-backend`) part dans un groupe « autres », jamais perdu.
 */
final class PermissionGroups
{
    /**
     * Verbes reconnus en tête de permission. `manage` est traité à part : il
     * nomme le groupe autant qu'il en fait partie.
     *
     * @var array<int, string>
     */
    private const VERBS = ['manage', 'view', 'create', 'update', 'delete', 'restore', 'force'];

    /**
     * @param  iterable<int, string>  $permissionNames
     * @return array<string, array<int, string>> groupe => permissions triées
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
     * Respecte l'ordre et le découpage de la config, mais n'affiche que les
     * permissions qui existent réellement en base — une config en avance sur
     * le seeder ne doit pas produire des cases sans objet. Ce que la config
     * oublie est récupéré à la fin plutôt que masqué.
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

            // Le nom du groupe est lui-même une permission dans la convention
            // Arkhe (`manage-users` chapeaute ses enfants) : on l'affiche avec.
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

        // Les macros `manage-X` en tête de leur groupe, le reste par ordre
        // alphabétique : on lit d'abord le raccourci, puis le détail.
        foreach ($groups as $resource => $names) {
            usort($names, static function (string $a, string $b): int {
                $aManage = str_starts_with($a, 'manage-');
                $bManage = str_starts_with($b, 'manage-');

                return $aManage === $bManage ? strcmp($a, $b) : ($aManage ? -1 : 1);
            });

            $groups[$resource] = $names;
        }

        // Le groupe fourre-tout ferme la marche, où qu'il soit apparu.
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
     * La ressource visée par une permission : le segment qui suit le verbe,
     * au pluriel pour que `view-user` et `manage-users` se rejoignent.
     */
    private static function resourceOf(string $permission): string
    {
        $parts = explode('-', $permission);

        if (count($parts) < 2 || ! in_array($parts[0], self::VERBS, true)) {
            return self::otherKey();
        }

        // `force-delete-user` : le verbe tient sur deux segments.
        if ($parts[0] === 'force' && count($parts) > 2) {
            array_shift($parts);
        }

        array_shift($parts);

        $resource = implode('-', $parts);

        return $resource === '' ? self::otherKey() : self::pluralise($resource);
    }

    /**
     * Clé de regroupement d'une ressource. Le pluriel n'est là que pour
     * rapprocher deux écritures de la même chose (`view-user` et
     * `manage-users`) — pas pour produire un anglais correct, d'où l'absence
     * de Str::plural. Le libellé affiché, lui, passe par {@see label()}.
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
     * Libellé lisible d'un groupe. Une traduction dédiée l'emporte quand elle
     * existe (`arkhe::arkhe.permissions.groups.users`), sinon on remet la clé
     * en français courant : « site-seos » n'a rien à faire dans une interface.
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
