<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Admin\Users;

use App\Models\User;
use Arkhe\Main\Repositories\RoleRepository;
use Arkhe\Main\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class UsersList extends Component
{
    public string $search = '';

    public string $role = '';

    protected function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ];
    }

    public function mount(): void
    {
        $this->getUsers();
    }

    public function getUsers(): LengthAwarePaginator
    {
        // Basic validation for the search
        $this->validate();

        $users = (new UserRepository)->getAllUsers();

        if ($this->search) {
            $searchTerm = $this->cleanSearchTerm($this->search);

            $users = $users->where(function ($query) use ($searchTerm): void {
                $query->where('first_name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('last_name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('email', 'like', '%'.$searchTerm.'%');
            });
        }

        if ($this->role) {
            $users = $users->whereHas('roles', function ($query): void {
                $query->where('name', $this->role);
            });
        }

        return $users->paginate(10);
    }

    public function updatedSearch(): void
    {
        // Livewire reloads automatically
    }

    public function updatedRole(): void
    {
        // Livewire reloads automatically
    }

    public function cleanSearchTerm(string $term): string
    {
        // Basic trimming to remove errors
        $term = trim($term);
        $term = strip_tags($term);
        $term = trim($term); // Clean up again

        // Limit length
        return substr($term, 0, 100);
    }

    public function getAllRoles(): Collection
    {
        return (new RoleRepository)->getAllRoles();
    }

    public function createUser(): RedirectResponse|Redirector
    {
        return redirect()->route('admin.users.create');
    }

    public function editUser(User $user): RedirectResponse|Redirector
    {
        return redirect()->route('admin.users.edit', $user->id);
    }

    public function canEditUser(User $user): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser->hasAnyRole(['root', 'admin'])) {
            return false;
        }

        if ($user->hasRole('root') && ! $currentUser->hasRole('root')) {
            return false;
        }

        return true;
    }

    public function canDeleteUser(User $user): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser->hasAnyRole(['root', 'admin'])) {
            return false;
        }

        if ($currentUser->id === $user->id) {
            return false;
        }

        if ($user->hasRole('root') && ! $currentUser->hasRole('root')) {
            return false;
        }

        return true;
    }

    public function deleteUser(int $userId): void
    {
        $userRepository = (new UserRepository);
        $user = $userRepository->find($userId);

        if ($user) {
            $userRepository->delete($user);
        }
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.users-list', [
            'users' => $this->getUsers(),
            'roles' => $this->getAllRoles(),
        ]);
    }
}
