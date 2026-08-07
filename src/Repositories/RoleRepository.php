<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * Colonnes autorisées au tri : le nom arrive de l'URL et part dans un
     * `orderBy`. `permissions_count` est trié sur l'agrégat, pas sur une
     * colonne de la table.
     *
     * @var array<int, string>
     */
    private const SORTABLE_FIELDS = ['name', 'guard_name', 'permissions_count'];

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        string $sort = 'name',
        string $direction = 'asc',
    ): LengthAwarePaginator {
        // La liste n'affiche plus que le *nombre* de permissions : on compte
        // côté SQL au lieu d'hydrater la collection complète pour chaque rôle
        // (root en porte autant qu'il en existe).
        $query = Role::query()->withCount('permissions');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $sort = in_array($sort, self::SORTABLE_FIELDS, true) ? $sort : 'name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sort, $direction)
            // Départage les ex æquo pour que la pagination reste stable.
            ->orderBy('id')
            ->paginate(max(1, $perPage));
    }

    public function find(int $id): ?Role
    {
        return Role::query()->with('permissions')->find($id);
    }

    public function findByName(string $name): ?Role
    {
        return Role::query()->where('name', $name)->first();
    }

    public function newModel(): Role
    {
        return new Role();
    }
}
