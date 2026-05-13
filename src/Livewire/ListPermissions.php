<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Livewire;

use Adhocrat\Arkhe\Contracts\PermissionRepositoryInterface;
use Adhocrat\Arkhe\Livewire\Forms\PermissionForm;
use Adhocrat\Arkhe\Services\PermissionService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ListPermissions extends Component
{
    use WithPagination;

    public PermissionForm $permissionForm;

    public ?int $selectedPermission = null;

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
        $this->permissionForm->reset();
        $this->selectedPermission = null;
        $this->showFormModal      = true;
    }

    public function openEdit(int $id, PermissionRepositoryInterface $repo): void
    {
        $permission = $repo->find($id);
        if ($permission === null) {
            return;
        }

        $this->resetErrorBag();
        $this->permissionForm->fillFromModel($permission);
        $this->selectedPermission = $id;
        $this->showFormModal      = true;
    }

    public function save(PermissionRepositoryInterface $repo, PermissionService $service): void
    {
        $this->permissionForm->id = $this->selectedPermission;

        $this->permissionForm->validate();
        $payload = $this->permissionForm->toArray();

        if ($this->selectedPermission === null) {
            $service->create($payload);
        } else {
            $permission = $repo->find($this->selectedPermission);
            if ($permission !== null) {
                $service->update($permission, $payload);
            }
        }

        $this->showFormModal = false;
        $this->permissionForm->reset();
        $this->selectedPermission = null;
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedPermission = $id;
        $this->showDeleteModal    = true;
    }

    public function delete(PermissionRepositoryInterface $repo, PermissionService $service): void
    {
        if ($this->selectedPermission === null) {
            return;
        }

        $permission = $repo->find($this->selectedPermission);
        if ($permission !== null) {
            $service->delete($permission);
        }

        $this->showDeleteModal    = false;
        $this->selectedPermission = null;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(PermissionRepositoryInterface $repo): View
    {
        $permissions = $repo->paginate(
            filters: ['search' => $this->search],
            perPage: $this->perPage,
        );

        return view('arkhe::livewire.list-permissions', [
            'permissions' => $permissions,
        ])->layout((string) config('arkhe.layout', 'arkhe::layouts.app'));
    }
}
