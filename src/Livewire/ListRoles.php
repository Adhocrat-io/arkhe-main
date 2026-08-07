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
     * Sortable columns, allow-listed: the name comes from the URL and ends up
     * in an `orderBy`.
     *
     * @var array<int, string>
     */
    private const SORTABLE_FIELDS = ['name', 'guard_name', 'permissions_count'];

    public function mount(): void
    {
        $this->authorize('view-role');
        $this->perPage = (int) config('arkhe.per_page', 15);
    }

    // ─── Legacy form ─────────────────────────────────────────────────────
    // Since 3.3, creation and edition have their own page ({@see EditRole}),
    // which also carries the permissions ticked per resource: the list no
    // longer shows a flyout. These methods and the hooks below stay in place
    // for the subclasses that override them, and will go in the next major.

    /**
     * @deprecated since 3.3 — role creation left the interface: roles come
     *             from `config('arkhe.roles')` and the seeder. To create one
     *             programmatically, go through `RoleService`.
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
     * @deprecated since 3.3 — see `arkhe.roles.edit` ({@see EditRole}).
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
     * @deprecated since 3.3 — saving happens on {@see EditRole}.
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
                // Canonical role: the service refuses, so say it rather than
                // closing the modal as if the deletion had happened.
                Flux::toast(variant: 'danger', text: __('arkhe::arkhe.roles.delete_canonical_refused'));
            }
        }

        $this->showDeleteModal = false;
        $this->selectedRole    = null;
        $this->resetPage();
    }

    /**
     * Closes the confirmation without deleting anything.
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
     * Sorts on a column, or flips the direction if it already carries the
     * sort.
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
     * The sort comes from the URL: an unknown field falls back to the default
     * rather than going into the query as-is. The repository filters again on
     * its side — but the host app can rebind it, and the guard must not rest
     * on an implementation we do not control.
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
     * An active filter changes what the empty state says: "no result for this
     * search" rather than "no role".
     */
    public function hasActiveFilters(): bool
    {
        return $this->search !== '';
    }

    /**
     * Role targeted by the pending confirmation, to name it in the modal.
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
     * Header counters: they do not follow the filters, they give the overall
     * state of the RBAC.
     *
     * @return array{roles: int, permissions: int}
     */
    private function globalStats(): array
    {
        return [
            'roles' => Role::query()->count(),
            // The only one of the two that cannot be read off the table: the
            // rows only give a permission count per role, never the total.
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

        // `availablePerms` and `canonicalResolver` are no longer consumed by
        // the package view since the form and the deletion left it. We keep
        // passing them: an app that published this view still expects them,
        // and their absence would be an `Undefined variable` on its side.
        // They will go along with the deprecated methods.
        return view('arkhe::livewire.list-roles', [
            'roles'              => $paginator,
            'availablePerms'     => $permissions->all()->pluck('name'),
            'canonicalResolver'  => fn (string $name): bool => $service->isCanonical($name),
            'stats'              => $this->globalStats(),
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
