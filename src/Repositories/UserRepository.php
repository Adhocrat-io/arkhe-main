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
    /**
     * Columns allowed for sorting.
     *
     * The list also lives in `ListUsers`, and that is deliberate: `$sort` is a
     * public parameter of the contract, which any application caller may feed
     * from a request. It ends up in an `orderBy()`, where it is not passed as
     * a binding — filtering it here is the last barrier.
     *
     * @var array<int, string>
     */
    private const SORTABLE_FIELDS = [
        'first_name', 'last_name', 'email', 'created_at', 'updated_at', 'id',
    ];

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
        // Eager-load roles: the list-users view iterates over
        // `$user->getRoleNames()` (see resources/views/livewire/list-users.blade.php),
        // which reads `$user->roles`. Without `with('roles')`, every paginated
        // user fires an identical Spatie query on `model_has_roles` — observed
        // in the wild: 9 identical N+1 queries for 9 users on screen. The SQL
        // cost is negligible; the Eloquent hydration cost, multiplied by the
        // number of users on the page, is not.
        $query = $this->query()->with('roles');

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
        $sort = in_array($sort, self::SORTABLE_FIELDS, true) ? $sort : 'created_at';

        return $query
            ->orderBy($sort, $direction)
            // Break ties so pagination stays stable.
            ->orderBy('id')
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
