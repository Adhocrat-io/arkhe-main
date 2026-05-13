<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface UserRepositoryInterface
{
    /**
     * @param  array{search?: string, role?: string}  $filters
     */
    public function paginate(
        array $filters = [],
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function find(int $id): ?Model;

    public function newModel(): Model;
}
