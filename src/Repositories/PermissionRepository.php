<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Repositories;

use Adhocrat\Arkhe\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Permission::query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query->orderBy('name')->paginate(max(1, $perPage));
    }

    public function find(int $id): ?Permission
    {
        return Permission::query()->find($id);
    }

    public function all(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    public function newModel(): Permission
    {
        return new Permission();
    }
}
