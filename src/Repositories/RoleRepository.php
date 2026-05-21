<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Role::query()->with('permissions');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query->orderBy('name')->paginate(max(1, $perPage));
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
