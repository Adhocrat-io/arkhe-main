<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Livewire\Forms\UserForm;
use Arkhe\Main\Services\UserService;
use Arkhe\Main\Support\RoleHierarchy;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
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

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $roleFilter = null;

    #[Url]
    public string $sortField = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    public int $perPage = 15;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    /**
     * Colonnes triables, en liste blanche : le nom arrive de l'URL et part
     * dans un `orderBy`.
     *
     * @var array<int, string>
     */
    private const SORTABLE_FIELDS = ['last_name', 'email', 'created_at'];

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
        $payload = array_merge($data, $this->userForm->toArray());

        $payload = $this->beforeSave($payload);

        if ($this->selectedUser === null) {
            $user = $service->create($payload);
            $this->afterCreate($user, $payload);
            Flux::toast(variant: 'success', text: __('arkhe::arkhe.users.created'));
        } else {
            $existing = $repository->find($this->selectedUser);
            if ($existing !== null) {
                if (! RoleHierarchy::canManage(Auth::user(), $existing)) {
                    abort(403);
                }

                $user = $service->update($existing, $payload);
                $this->afterUpdate($user, $payload);
                Flux::toast(variant: 'success', text: __('arkhe::arkhe.users.updated'));
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

    /**
     * Referme la confirmation sans rien supprimer.
     */
    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->selectedUser = null;
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

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.users.deleted'));

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
     * que de partir tel quel dans la requête.
     */
    private function safeSortField(): string
    {
        return in_array($this->sortField, self::SORTABLE_FIELDS, strict: true)
            ? $this->sortField
            : 'created_at';
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

    /**
     * Un filtre actif change le discours de l'état vide : « aucun résultat
     * pour cette recherche » plutôt que « aucun utilisateur ».
     */
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || ($this->roleFilter !== null && $this->roleFilter !== '');
    }

    /**
     * Utilisateur visé par la confirmation en cours, pour le nommer dans la
     * modale plutôt que de parler d'« cet utilisateur ».
     */
    #[Computed]
    public function pendingDeleteUser(): ?Model
    {
        if ($this->selectedUser === null || ! $this->showDeleteModal) {
            return null;
        }

        return app(UserRepositoryInterface::class)->find($this->selectedUser);
    }

    /**
     * Compteurs d'en-tête, tous utilisateurs confondus : ils ne suivent pas
     * les filtres, ils donnent l'état global de la base.
     *
     * @return array{total: int, verified: int, unverified: int, tracks_verification: bool, without_role: int}
     */
    private function globalStats(UserRepositoryInterface $repository): array
    {
        $model = $repository->newModel();

        $total = $model->newQuery()->count();

        // `email_verified_at` vient des starter kits Laravel, pas d'Arkhe :
        // une app qui a retiré la colonne ne doit pas faire tomber la page.
        $tracksVerification = Schema::hasColumn($model->getTable(), 'email_verified_at');
        $verified = $tracksVerification
            ? $model->newQuery()->whereNotNull('email_verified_at')->count()
            : 0;

        return [
            'total' => $total,
            'verified' => $verified,
            'unverified' => $tracksVerification ? $total - $verified : 0,
            'tracks_verification' => $tracksVerification,
            'without_role' => $model->newQuery()->doesntHave('roles')->count(),
        ];
    }

    public function render(UserRepositoryInterface $repository): View
    {
        $users = $repository->paginate(
            filters: [
                'search' => $this->search,
                'role' => (string) $this->roleFilter,
            ],
            sort: $this->safeSortField(),
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
            'stats' => $this->globalStats($repository),
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
