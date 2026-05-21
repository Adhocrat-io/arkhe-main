<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Livewire\Forms\RoleForm;
use Arkhe\Main\Services\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ListRoles extends Component
{
    use WithPagination;

    public RoleForm $roleForm;

    public ?int $selectedRole = null;

    public string $search = '';

    public int $perPage = 15;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $this->authorize('view-role');
        $this->perPage = (int) config('arkhe.per_page', 15);
    }

    public function openCreate(): void
    {
        $this->authorize('create-role');

        $this->resetErrorBag();
        $this->roleForm->reset();
        $this->selectedRole  = null;
        $this->showFormModal = true;
    }

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
        } else {
            $existing = $roles->find($this->selectedRole);
            if ($existing !== null) {
                $role = $service->update($existing, $payload);
                $this->afterUpdate($role, $payload);
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
            } catch (AuthorizationException) {
                // canonical role — silently skip
            }
        }

        $this->showDeleteModal = false;
        $this->selectedRole    = null;
        $this->resetPage();
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

    public function render(
        RoleRepositoryInterface $roles,
        PermissionRepositoryInterface $permissions,
        RoleService $service,
    ): View {
        $paginator = $roles->paginate(
            filters: ['search' => $this->search],
            perPage: $this->perPage,
        );

        return view('arkhe::livewire.list-roles', [
            'roles'              => $paginator,
            'availablePerms'     => $permissions->all()->pluck('name'),
            'canonicalResolver'  => fn (string $name): bool => $service->isCanonical($name),
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
