<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Livewire;

use Adhocrat\Arkhe\Contracts\UserRepositoryInterface;
use Adhocrat\Arkhe\Livewire\Forms\UserForm;
use Adhocrat\Arkhe\Services\UserService;
use Adhocrat\Arkhe\Support\RoleHierarchy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ListUsers extends Component
{
    use WithFileUploads;
    use WithPagination;

    public UserForm $userForm;

    public ?int $selectedUser = null;

    public string $search = '';

    public ?string $roleFilter = null;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

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
        $this->userForm->reset();
        $this->selectedUser  = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id, UserRepositoryInterface $repository): void
    {
        $user = $repository->find($id);
        if ($user === null) {
            return;
        }

        $this->resetErrorBag();
        $this->userForm->fillFromModel($user);
        $this->selectedUser  = $id;
        $this->showFormModal = true;
    }

    public function save(UserRepositoryInterface $repository, UserService $service): void
    {
        // Keep the form's id in sync with the component's selectedUser so that
        // Rule::unique(...)->ignore() and the create-vs-edit branching in
        // passwordRules() see the correct value even if a prior Livewire hop
        // (e.g. file upload) reset the form property.
        $this->userForm->id = $this->selectedUser;

        $data = $this->userForm->validate();
        // validate() returns rule keys; pass the full form payload (incl. avatar).
        $payload = array_merge($data, $this->userForm->toArray());

        if ($this->selectedUser === null) {
            $service->create($payload);
        } else {
            $user = $repository->find($this->selectedUser);
            if ($user !== null) {
                $service->update($user, $payload);
            }
        }

        $this->showFormModal = false;
        $this->userForm->reset();
        $this->selectedUser = null;
        $this->resetPage();
    }

    public function confirmDelete(int $id, UserRepositoryInterface $repository): void
    {
        $target = $repository->find($id);

        if ($target === null || ! RoleHierarchy::canManage(Auth::user(), $target)) {
            abort(403);
        }

        $this->selectedUser    = $id;
        $this->showDeleteModal = true;
    }

    public function delete(UserRepositoryInterface $repository, UserService $service): void
    {
        if ($this->selectedUser === null) {
            return;
        }

        $user = $repository->find($this->selectedUser);
        if ($user === null) {
            $this->showDeleteModal = false;
            $this->selectedUser    = null;

            return;
        }

        if (! RoleHierarchy::canManage(Auth::user(), $user)) {
            abort(403);
        }

        $service->delete($user);

        $this->showDeleteModal = false;
        $this->selectedUser    = null;
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField     = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search     = '';
        $this->roleFilter = null;
        $this->resetPage();
    }

    public function render(UserRepositoryInterface $repository): View
    {
        $users = $repository->paginate(
            filters: [
                'search' => $this->search,
                'role'   => (string) $this->roleFilter,
            ],
            sort: $this->sortField,
            direction: $this->sortDirection,
            perPage: $this->perPage,
        );

        $currentAvatarUrl = null;
        if ($this->selectedUser !== null) {
            $editing = $repository->find($this->selectedUser);
            if ($editing !== null) {
                $currentAvatarUrl = $editing->avatar_url ?? null;
            }
        }

        $assignable     = RoleHierarchy::rolesAssignableBy(Auth::user());
        $availableRoles = Role::query()->orderBy('name')->pluck('name');

        return view('arkhe::livewire.list-users', [
            'users'            => $users,
            'availableRoles'   => $availableRoles, // unfiltered: drives the filter dropdown
            'assignableRoles'  => $availableRoles->filter(fn (string $name): bool => in_array($name, $assignable, true))->values(),
            'availablePerms'   => Permission::query()->orderBy('name')->pluck('name'),
            'currentAvatarUrl' => $currentAvatarUrl,
        ])->layout((string) config('arkhe.layout', 'arkhe::layouts.app'));
    }
}
