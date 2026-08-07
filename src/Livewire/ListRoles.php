<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Livewire\Forms\RoleForm;
use Arkhe\Main\Services\RoleService;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ListRoles extends Component
{
    use WithPagination;

    public RoleForm $roleForm;

    public ?int $selectedRole = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public int $perPage = 15;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    /**
     * Colonnes triables, en liste blanche : le nom arrive de l'URL et part
     * dans un `orderBy`.
     *
     * @var array<int, string>
     */
    private const SORTABLE_FIELDS = ['name', 'guard_name', 'permissions_count'];

    public function mount(): void
    {
        $this->authorize('view-role');
        $this->perPage = (int) config('arkhe.per_page', 15);
    }

    // ─── Formulaire hérité ───────────────────────────────────────────────
    // Depuis la 3.3, création et édition ont leur page ({@see EditRole}), qui
    // porte aussi les permissions cochées par ressource : la liste ne montre
    // plus de flyout. Ces méthodes et les hooks qui suivent restent en place
    // pour les sous-classes qui les surchargent, et partiront à la prochaine
    // majeure.

    /**
     * @deprecated depuis la 3.3 — la création de rôle a quitté l'interface :
     *             les rôles viennent de `config('arkhe.roles')` et du seeder.
     *             Pour en créer un par programme, passez par `RoleService`.
     */
    public function openCreate(): void
    {
        $this->authorize('create-role');

        $this->resetErrorBag();
        $this->roleForm->reset();
        $this->selectedRole  = null;
        $this->showFormModal = true;
    }

    /**
     * @deprecated depuis la 3.3 — voir `arkhe.roles.edit` ({@see EditRole}).
     */
    public function openEdit(int $id, RoleRepositoryInterface $roles, RoleService $service): void
    {
        $this->authorize('update-role');

        $role = $roles->find($id);
        if ($role === null) {
            return;
        }

        $this->resetErrorBag();
        $this->roleForm->fillFromModel($role, $service->isCanonical($role->name));
        $this->selectedRole  = $id;
        $this->showFormModal = true;
    }

    /**
     * @deprecated depuis la 3.3 — l'enregistrement se fait sur {@see EditRole}.
     */
    public function save(RoleRepositoryInterface $roles, RoleService $service): void
    {
        $this->authorize($this->selectedRole === null ? 'create-role' : 'update-role');

        $this->roleForm->id = $this->selectedRole;

        $this->roleForm->validate();
        $payload = $this->roleForm->toArray();

        $payload = $this->beforeSave($payload);

        if ($this->selectedRole === null) {
            $role = $service->create($payload);
            $this->afterCreate($role, $payload);
            Flux::toast(variant: 'success', text: __('arkhe::arkhe.roles.created'));
        } else {
            $existing = $roles->find($this->selectedRole);
            if ($existing !== null) {
                $role = $service->update($existing, $payload);
                $this->afterUpdate($role, $payload);
                Flux::toast(variant: 'success', text: __('arkhe::arkhe.roles.updated'));
            }
        }

        $this->showFormModal = false;
        $this->roleForm->reset();
        $this->selectedRole = null;
        $this->resetPage();
    }

    public function confirmDelete(int $id, RoleRepositoryInterface $roles, RoleService $service): void
    {
        $this->authorize('delete-role');

        $role = $roles->find($id);
        if ($role === null || $service->isCanonical($role->name)) {
            return;
        }

        $this->selectedRole    = $id;
        $this->showDeleteModal = true;
    }

    public function delete(RoleRepositoryInterface $roles, RoleService $service): void
    {
        $this->authorize('delete-role');

        if ($this->selectedRole === null) {
            return;
        }

        $role = $roles->find($this->selectedRole);

        if ($role !== null) {
            $this->beforeDelete($role);

            try {
                $service->delete($role);
                Flux::toast(variant: 'success', text: __('arkhe::arkhe.roles.deleted'));
            } catch (AuthorizationException) {
                // Rôle canonique : le service refuse, on le dit plutôt que de
                // refermer la modale comme si la suppression avait eu lieu.
                Flux::toast(variant: 'danger', text: __('arkhe::arkhe.roles.delete_canonical_refused'));
            }
        }

        $this->showDeleteModal = false;
        $this->selectedRole    = null;
        $this->resetPage();
    }

    /**
     * Referme la confirmation sans rien supprimer.
     */
    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->selectedRole    = null;
    }

    // ─── Extensibility hooks ─────────────────────────────────────────────
    // Override these in a subclass declared via
    // `config('arkhe.components.list-roles')` to inject host-app behaviour.

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function beforeSave(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterCreate(\Spatie\Permission\Models\Role $role, array $payload): void
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterUpdate(\Spatie\Permission\Models\Role $role, array $payload): void
    {
    }

    protected function beforeDelete(\Spatie\Permission\Models\Role $role): void
    {
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Trie sur une colonne, ou inverse le sens si elle porte déjà le tri.
     */
    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE_FIELDS, strict: true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Le tri arrive de l'URL : un champ inconnu retombe sur le défaut plutôt
     * que de partir tel quel dans la requête. Le repository refiltre de son
     * côté — mais il est rebindable par l'app hôte, et la garde ne doit pas
     * reposer sur une implémentation qu'on ne maîtrise pas.
     */
    private function safeSortField(): string
    {
        return in_array($this->sortField, self::SORTABLE_FIELDS, strict: true)
            ? $this->sortField
            : 'name';
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Un filtre actif change le discours de l'état vide : « aucun résultat
     * pour cette recherche » plutôt que « aucun rôle ».
     */
    public function hasActiveFilters(): bool
    {
        return $this->search !== '';
    }

    /**
     * Rôle visé par la confirmation en cours, pour le nommer dans la modale.
     */
    #[Computed]
    public function pendingDeleteRole(): ?Role
    {
        if ($this->selectedRole === null || ! $this->showDeleteModal) {
            return null;
        }

        return app(RoleRepositoryInterface::class)->find($this->selectedRole);
    }

    /**
     * Compteurs d'en-tête : ils ne suivent pas les filtres, ils donnent
     * l'état global du RBAC.
     *
     * @return array{roles: int, permissions: int}
     */
    private function globalStats(): array
    {
        return [
            'roles' => Role::query()->count(),
            // Le seul des deux qui ne se lise pas dans la table : les lignes
            // ne donnent qu'un nombre de permissions par rôle, jamais le total.
            'permissions' => Permission::query()->count(),
        ];
    }

    public function render(
        RoleRepositoryInterface $roles,
        PermissionRepositoryInterface $permissions,
        RoleService $service,
    ): View {
        $paginator = $roles->paginate(
            filters: ['search' => $this->search],
            perPage: $this->perPage,
            sort: $this->safeSortField(),
            direction: $this->sortDirection,
        );

        // `availablePerms` et `canonicalResolver` ne sont plus consommés par la
        // vue du paquet depuis que le formulaire et la suppression l'ont
        // quittée. On continue de les passer : une app qui a publié cette vue
        // les attend encore, et leur absence serait un `Undefined variable`
        // chez elle. Ils partiront avec les méthodes dépréciées.
        return view('arkhe::livewire.list-roles', [
            'roles'              => $paginator,
            'availablePerms'     => $permissions->all()->pluck('name'),
            'canonicalResolver'  => fn (string $name): bool => $service->isCanonical($name),
            'stats'              => $this->globalStats(),
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
