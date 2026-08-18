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
     * Columns allowed for sorting: the name comes from the URL and ends up in
     * an `orderBy`. `permissions_count` sorts on the aggregate, not on a
     * column of the table.
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
        // The list only shows the permission *count* now: count it in SQL
        // rather than hydrating the full collection for every role (root
        // carries as many as exist).
        $query = Role::query()->withCount('permissions');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $sort = in_array($sort, self::SORTABLE_FIELDS, true) ? $sort : 'name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sort, $direction)
            // Break ties so pagination stays stable.
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
