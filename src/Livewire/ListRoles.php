<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Livewire;

use Adhocrat\Arkhe\Contracts\PermissionRepositoryInterface;
use Adhocrat\Arkhe\Contracts\RoleRepositoryInterface;
use Adhocrat\Arkhe\Livewire\Forms\RoleForm;
use Adhocrat\Arkhe\Services\RoleService;
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
        $this->perPage = (int) config('arkhe.per_page', 15);
    }

    public function openCreate(): void
    {
        $this->resetErrorBag();
        $this->roleForm->reset();
        $this->selectedRole  = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id, RoleRepositoryInterface $roles, RoleService $service): void
    {
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
        $this->roleForm->id = $this->selectedRole;

        $this->roleForm->validate();
        $payload = $this->roleForm->toArray();

        if ($this->selectedRole === null) {
            $service->create($payload);
        } else {
            $role = $roles->find($this->selectedRole);
            if ($role !== null) {
                $service->update($role, $payload);
            }
        }

        $this->showFormModal = false;
        $this->roleForm->reset();
        $this->selectedRole = null;
        $this->resetPage();
    }

    public function confirmDelete(int $id, RoleRepositoryInterface $roles, RoleService $service): void
    {
        $role = $roles->find($id);
        if ($role === null || $service->isCanonical($role->name)) {
            return;
        }

        $this->selectedRole    = $id;
        $this->showDeleteModal = true;
    }

    public function delete(RoleRepositoryInterface $roles, RoleService $service): void
    {
        if ($this->selectedRole === null) {
            return;
        }

        $role = $roles->find($this->selectedRole);

        if ($role !== null) {
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
        ])->layout((string) config('arkhe.layout', 'arkhe::layouts.app'));
    }
}
