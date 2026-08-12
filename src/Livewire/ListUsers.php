<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Livewire\Forms\UserForm;
use Arkhe\Main\Services\UserService;
use Arkhe\Main\Support\RoleHierarchy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
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
        $this->authorize('view-user');
        $this->perPage = (int) config('arkhe.per_page', 15);
    }

    public function openCreate(): void
    {
        $this->authorize('create-user');

        $this->resetErrorBag();
        $this->userForm->reset();
        $this->selectedUser = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id, UserRepositoryInterface $repository): void
    {
        $this->authorize('update-user');

        $user = $repository->find($id);
        if ($user === null) {
            return;
        }

        if (! RoleHierarchy::canManage(Auth::user(), $user)) {
            abort(403);
        }

        $this->resetErrorBag();
        $this->userForm->fillFromModel($user);
        $this->selectedUser = $id;
        $this->showFormModal = true;
    }

    public function save(UserRepositoryInterface $repository, UserService $service): void
    {
        $this->authorize($this->selectedUser === null ? 'create-user' : 'update-user');

        $this->userForm->id = $this->selectedUser;

        $data = $this->userForm->validate();
        // validate() returns rule keys; pass the full form payload (incl. avatar).
        $payload = array_merge($data, $this->userForm->toPayload());

        $payload = $this->beforeSave($payload);

        if ($this->selectedUser === null) {
            $user = $service->create($payload);
            $this->afterCreate($user, $payload);
        } else {
            $existing = $repository->find($this->selectedUser);
            if ($existing !== null) {
                if (! RoleHierarchy::canManage(Auth::user(), $existing)) {
                    abort(403);
                }

                $user = $service->update($existing, $payload);
                $this->afterUpdate($user, $payload);
            }
        }

        $this->showFormModal = false;
        $this->userForm->reset();
        $this->selectedUser = null;
        $this->resetPage();
    }

    public function confirmDelete(int $id, UserRepositoryInterface $repository): void
    {
        $this->authorize('delete-user');

        $target = $repository->find($id);

        if ($target === null || ! RoleHierarchy::canManage(Auth::user(), $target)) {
            abort(403);
        }

        $this->selectedUser = $id;
        $this->showDeleteModal = true;
    }

    public function delete(UserRepositoryInterface $repository, UserService $service): void
    {
        $this->authorize('delete-user');

        if ($this->selectedUser === null) {
            return;
        }

        $user = $repository->find($this->selectedUser);
        if ($user === null) {
            $this->showDeleteModal = false;
            $this->selectedUser = null;

            return;
        }

        if (! RoleHierarchy::canManage(Auth::user(), $user)) {
            abort(403);
        }

        $this->beforeDelete($user);

        $service->delete($user);

        $this->showDeleteModal = false;
        $this->selectedUser = null;
        $this->resetPage();
    }

    // ─── Extensibility hooks ─────────────────────────────────────────────
    // Empty by default; override in a subclass declared via
    // `config('arkhe.components.list-users')` to plug host-app behaviour
    // (newsletter sync, audit log, custom field handling) without forking
    // the component. Hooks receive the saved/about-to-delete user model and
    // the merged form payload (validated rules + form properties).

    /**
     * Called right after validation and just before the service call. Return
     * the payload to forward to `UserService::create|update`.
     *
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
    protected function afterCreate(Model $user, array $payload): void {}

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterUpdate(Model $user, array $payload): void {}

    protected function beforeDelete(Model $user): void {}

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
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
        $this->search = '';
        $this->roleFilter = null;
        $this->resetPage();
    }

    public function render(UserRepositoryInterface $repository): View
    {
        $users = $repository->paginate(
            filters: [
                'search' => $this->search,
                'role' => (string) $this->roleFilter,
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

        $assignable = RoleHierarchy::rolesAssignableBy(Auth::user());
        $availableRoles = Role::query()->orderBy('name')->pluck('name');

        return view('arkhe::livewire.list-users', [
            'users' => $users,
            'availableRoles' => $availableRoles, // unfiltered: drives the filter dropdown
            'assignableRoles' => $availableRoles->filter(fn (string $name): bool => in_array($name, $assignable, true))->values(),
            'availablePerms' => Permission::query()->orderBy('name')->pluck('name'),
            'currentAvatarUrl' => $currentAvatarUrl,
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
