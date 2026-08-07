<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
        private readonly EventsDispatcher $events,
        private readonly ConfigRepository $config,
        private readonly PermissionRegistrar $registrar,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{name: string, guard_name?: string, permissions?: array<int, string>}  $data
     */
    public function create(array $data): Role
    {
        $this->assertNameIsAvailable((string) $data['name']);

        $role = $this->repository->newModel();
        $role->forceFill([
            'name' => (string) $data['name'],
            'guard_name' => (string) ($data['guard_name'] ?? 'web'),
        ])->save();

        $this->syncPermissions($role, $data['permissions'] ?? null);

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new RoleCreated($role));

        return $role->refresh();
    }

    /**
     * @param  array{name?: string, guard_name?: string, permissions?: array<int, string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        $isCanonical = $this->isCanonical($role->name);

        // Canonical roles keep their name immutable to avoid breaking middleware
        // and config lookups; only their permissions are editable.
        if (! $isCanonical && array_key_exists('name', $data) && (string) $data['name'] !== '') {
            $role->name = (string) $data['name'];
        }

        if (! $isCanonical && array_key_exists('guard_name', $data) && (string) $data['guard_name'] !== '') {
            $role->guard_name = (string) $data['guard_name'];
        }

        $role->save();

        $this->syncPermissions($role, $data['permissions'] ?? null);

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new RoleUpdated($role));

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        if ($this->isCanonical($role->name)) {
            throw new AuthorizationException(
                "The role [{$role->name}] is canonical to Arkhe and cannot be deleted."
            );
        }

        $role->delete();

        $this->registrar->forgetCachedPermissions();
        $this->events->dispatch(new RoleDeleted($role));
    }

    public function isCanonical(string $name): bool
    {
        return in_array($name, array_values((array) $this->config->get('arkhe.roles', [])), true);
    }

    /**
     * Refuse de créer un rôle dont le nom ouvre une porte à lui seul.
     *
     * Le middleware d'accès au back-office accepte, en compatibilité V2, que
     * `admin.roles` liste des noms de rôles bruts. Un nom qui y figure vaut
     * donc l'entrée, sans détenir `access-backend` : fabriquer un rôle
     * homonyme et se l'attribuer suffirait. On réserve ces noms, comme les
     * noms canoniques.
     *
     * @throws AuthorizationException
     */
    private function assertNameIsAvailable(string $name): void
    {
        $actor = $this->auth->guard()->user();

        if ($actor === null && app()->runningInConsole()) {
            return;
        }

        if ($actor !== null
            && method_exists($actor, 'hasRole')
            && $actor->hasRole((string) $this->config->get('arkhe.roles.root'))) {
            return;
        }

        if ($this->isCanonical($name) || $this->grantsBackendAccessByName($name)) {
            throw new AuthorizationException(
                "The role name [{$name}] is reserved by Arkhe."
            );
        }
    }

    /**
     * Le nom figure-t-il tel quel dans `admin.roles` ? Auquel cas le porter
     * vaut l'accès au back-office, sans passer par une permission.
     */
    private function grantsBackendAccessByName(string $name): bool
    {
        $configured = (array) $this->config->get('arkhe.admin.roles', []);
        $rolesMap = (array) $this->config->get('arkhe.roles', []);

        foreach ($configured as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            // Une clé résolue par la map désigne un rôle canonique, déjà
            // couvert par `isCanonical()`. Seules les clés brutes comptent ici.
            if (array_key_exists($key, $rolesMap)) {
                continue;
            }

            if ($key === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>|null  $permissionNames
     */
    private function syncPermissions(Role $role, ?array $permissionNames): void
    {
        if ($permissionNames === null) {
            return;
        }

        $this->assertCanGrant($role, $permissionNames);

        $role->syncPermissions($permissionNames);
    }

    /**
     * On n'accorde que ce qu'on détient soi-même.
     *
     * Sans cette garde, quiconque peut modifier un rôle peut s'y attribuer
     * n'importe quelle permission — à commencer par celle qui ouvre la page
     * des rôles — et devenir root en un enregistrement. La permission
     * `update-role` suffisait à prendre la main sur toute l'application.
     *
     * La comparaison porte sur le *delta* : retirer une permission qu'on n'a
     * pas reste permis (on ne s'élève pas en enlevant), et réenregistrer un
     * rôle sans y toucher ne demande rien de plus.
     *
     * Les appels sans acteur (console, jobs) passent : ils supposent déjà un
     * accès au serveur, où la question ne se pose plus.
     *
     * @param  array<int, string>  $permissionNames
     *
     * @throws AuthorizationException
     */
    private function assertCanGrant(Role $role, array $permissionNames): void
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        if ($actor === null) {
            if (app()->runningInConsole()) {
                return;
            }

            throw new AuthorizationException('Cannot change role permissions without an authenticated actor.');
        }

        // Un acteur qui détient déjà tout n'a rien à se voir refuser.
        if (method_exists($actor, 'hasRole') && $actor->hasRole((string) $this->config->get('arkhe.roles.root'))) {
            return;
        }

        $current = $role->exists
            ? $role->permissions->pluck('name')->all()
            : [];

        $granted = array_diff($permissionNames, $current);

        foreach ($granted as $permission) {
            if (! method_exists($actor, 'can') || ! $actor->can($permission)) {
                throw new AuthorizationException(
                    "You are not allowed to grant the permission [{$permission}]."
                );
            }
        }
    }
}
