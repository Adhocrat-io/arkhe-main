<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Livewire\Forms\PermissionForm;
use Arkhe\Main\Services\PermissionService;
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
        $this->authorize('view-permission');
        $this->perPage = (int) config('arkhe.per_page', 15);
    }

    public function openCreate(): void
    {
        $this->authorize('create-permission');

        $this->resetErrorBag();
        $this->permissionForm->reset();
        $this->selectedPermission = null;
        $this->showFormModal      = true;
    }

    public function openEdit(int $id, PermissionRepositoryInterface $repo): void
    {
        $this->authorize('update-permission');

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
        $this->authorize($this->selectedPermission === null ? 'create-permission' : 'update-permission');

        $this->permissionForm->id = $this->selectedPermission;

        $this->permissionForm->validate();
        $payload = $this->permissionForm->toPayload();

        $payload = $this->beforeSave($payload);

        if ($this->selectedPermission === null) {
            $permission = $service->create($payload);
            $this->afterCreate($permission, $payload);
        } else {
            $existing = $repo->find($this->selectedPermission);
            if ($existing !== null) {
                $permission = $service->update($existing, $payload);
                $this->afterUpdate($permission, $payload);
            }
        }

        $this->showFormModal = false;
        $this->permissionForm->reset();
        $this->selectedPermission = null;
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete-permission');

        $this->selectedPermission = $id;
        $this->showDeleteModal    = true;
    }

    public function delete(PermissionRepositoryInterface $repo, PermissionService $service): void
    {
        $this->authorize('delete-permission');

        if ($this->selectedPermission === null) {
            return;
        }

        $permission = $repo->find($this->selectedPermission);
        if ($permission !== null) {
            $this->beforeDelete($permission);
            $service->delete($permission);
        }

        $this->showDeleteModal    = false;
        $this->selectedPermission = null;
        $this->resetPage();
    }

    // ─── Extensibility hooks ─────────────────────────────────────────────
    // Override in a subclass declared via
    // `config('arkhe.components.list-permissions')` to add custom behaviour.

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
    protected function afterCreate(\Spatie\Permission\Models\Permission $permission, array $payload): void
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterUpdate(\Spatie\Permission\Models\Permission $permission, array $payload): void
    {
    }

    protected function beforeDelete(\Spatie\Permission\Models\Permission $permission): void
    {
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
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
