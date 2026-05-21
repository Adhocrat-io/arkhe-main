<?php

declare(strict_types=1);

namespace Arkhe\Main\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * @param  array{search?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Role;

    public function findByName(string $name): ?Role;

    public function newModel(): Role;
}
