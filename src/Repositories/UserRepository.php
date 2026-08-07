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
     * Colonnes autorisées au tri.
     *
     * La liste vit aussi dans `ListUsers`, et c'est voulu : `$sort` est un
     * paramètre public du contrat, que n'importe quel appelant applicatif peut
     * alimenter depuis une requête. Il finit dans un `orderBy()`, où il n'est
     * pas passé en binding — le filtrer ici est la dernière barrière.
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
        // Eager-load les rôles : la vue list-users itère sur
        // `$user->getRoleNames()` (cf. resources/views/livewire/list-users.blade.php),
        // qui lit `$user->roles`. Sans `with('roles')`, chaque user paginé
        // déclenche une query Spatie identique sur `model_has_roles` —
        // observé in vivo : 9 queries N+1 identiques pour 9 users affichés.
        // Coût SQL négligeable mais surtout coût d'hydratation Eloquent
        // multiplié par le nombre de users dans la page.
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
            // Départage les ex æquo pour que la pagination reste stable.
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
