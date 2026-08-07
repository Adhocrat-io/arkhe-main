<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Events\PermissionCreated;
use Arkhe\Main\Events\PermissionDeleted;
use Arkhe\Main\Events\PermissionUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
        private readonly EventsDispatcher $events,
        private readonly PermissionRegistrar $registrar,
        private readonly ConfigRepository $config,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{name: string, guard_name?: string}  $data
     */
    public function create(array $data): Permission
    {
        $this->assertNameIsAvailable((string) $data['name']);

        $permission = $this->repository->newModel();
        $permission->forceFill([
            'name' => (string) $data['name'],
            'guard_name' => (string) ($data['guard_name'] ?? 'web'),
        ])->save();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionCreated($permission));

        return $permission;
    }

    /**
     * @param  array{name?: string, guard_name?: string}  $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        $this->assertCanAlter($permission);

        if (array_key_exists('name', $data) && (string) $data['name'] !== '') {
            $this->assertNameIsAvailable((string) $data['name'], $permission);
            $permission->name = (string) $data['name'];
        }

        if (array_key_exists('guard_name', $data) && (string) $data['guard_name'] !== '') {
            $permission->guard_name = (string) $data['guard_name'];
        }

        $permission->save();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionUpdated($permission));

        return $permission;
    }

    public function delete(Permission $permission): void
    {
        $this->assertCanAlter($permission);

        $permission->delete();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new PermissionDeleted($permission));
    }

    /**
     * Une permission déclarée dans `config('arkhe.permissions')` est canonique :
     * le code du paquet s'y réfère en dur — `access-backend` garde l'entrée du
     * back-office, `manage-roles` sa zone sensible. La renommer ou la supprimer
     * couperait ces gardes, pour tout le monde et sans retour possible depuis
     * l'interface.
     */
    public function isCanonical(string $name): bool
    {
        return in_array($name, (array) $this->config->get('arkhe.permissions', []), true);
    }

    /**
     * Refuse de toucher à une permission canonique, et à une permission que
     * l'acteur ne détient pas lui-même.
     *
     * Sans cette garde, renommer suffisait à tout prendre : les tables pivots
     * référencent l'identifiant, pas le nom. Rebaptiser `view-user` en
     * `manage-roles` transformait d'un coup tous ses porteurs en
     * administrateurs, sans jamais toucher à un rôle ni à un compte — donc
     * sans croiser les gardes de RoleService ni de UserService.
     *
     * @throws AuthorizationException
     */
    private function assertCanAlter(Permission $permission): void
    {
        if ($this->isCanonical($permission->name)) {
            throw new AuthorizationException(
                "The permission [{$permission->name}] is canonical to Arkhe and cannot be renamed or deleted."
            );
        }

        $actor = $this->actor();

        if ($actor === null) {
            return; // console : l'appelant a déjà l'accès au serveur.
        }

        if ($this->isRoot($actor)) {
            return;
        }

        if (! method_exists($actor, 'can') || ! $actor->can($permission->name)) {
            throw new AuthorizationException(
                "You are not allowed to alter the permission [{$permission->name}]."
            );
        }
    }

    /**
     * Refuse d'écrire un nom que l'acteur ne détient pas.
     *
     * C'est l'autre moitié du verrou : sans elle, on renommerait une
     * permission anodine *vers* un nom puissant. Le nom canonique est refusé
     * à tous — deux lignes portant `manage-roles` rendraient les
     * vérifications de Spatie non déterministes.
     *
     * @throws AuthorizationException
     */
    private function assertNameIsAvailable(string $name, ?Permission $current = null): void
    {
        if ($current !== null && $current->name === $name) {
            return;
        }

        if ($this->isCanonical($name)) {
            throw new AuthorizationException(
                "The permission name [{$name}] is reserved by Arkhe."
            );
        }

        $actor = $this->actor();

        if ($actor === null || $this->isRoot($actor)) {
            return;
        }

        // Un nom qui existe déjà et que l'acteur ne détient pas serait une
        // prise de contrôle par collision.
        $exists = Permission::query()->where('name', $name)->exists();

        if ($exists && (! method_exists($actor, 'can') || ! $actor->can($name))) {
            throw new AuthorizationException(
                "You are not allowed to take over the permission [{$name}]."
            );
        }
    }

    /**
     * L'acteur courant, ou null hors contexte HTTP (console, jobs).
     */
    private function actor(): ?Model
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        if ($actor === null && ! app()->runningInConsole()) {
            throw new AuthorizationException('Cannot change permissions without an authenticated actor.');
        }

        return $actor;
    }

    private function isRoot(Model $actor): bool
    {
        return method_exists($actor, 'hasRole')
            && $actor->hasRole((string) $this->config->get('arkhe.roles.root'));
    }
}
