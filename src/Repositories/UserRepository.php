<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function paginate(
        array $filters = [],
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $this->query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $query->whereHas('roles', function (Builder $q) use ($role): void {
                $q->where('name', $role);
            });
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->paginate(max(1, $perPage));
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function newModel(): Model
    {
        $class = $this->resolveUserModel();

        return new $class();
    }

    private function query(): Builder
    {
        return $this->newModel()->newQuery();
    }

    /**
     * @return class-string<Model>
     */
    private function resolveUserModel(): string
    {
        /** @var class-string<Model>|null $configured */
        $configured = $this->config->get('arkhe.user_model');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        /** @var class-string<Model> $default */
        $default = $this->config->get('auth.providers.users.model', \App\Models\User::class);

        return $default;
    }
}
