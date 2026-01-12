<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Admin\Users;

use App\Models\User;
use Arkhe\Main\DataTransferObjects\UserDto;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Livewire\Forms\Admin\Users\UserEditForm;
use Arkhe\Main\Repositories\RoleRepository;
use Arkhe\Main\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class UserEdit extends Component
{
    public UserEditForm $userEditForm;

    public ?User $user = null;

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->userEditForm->setUser($user);
    }

    public function getAllRoles(): Collection
    {
        return (new RoleRepository)->getAllRoles();
    }

    public function canEditUser(User $user): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if ($user->hasRole(UserRoleEnum::ROOT->value) && ! $currentUser->hasRole(UserRoleEnum::ROOT->value)) {
            return false;
        }

        return true;
    }

    public function canDeleteUser(User $user): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser->hasAnyRole([UserRoleEnum::ROOT->value, UserRoleEnum::ADMIN->value])) {
            return false;
        }

        if ($currentUser->id === $user->id) {
            return false;
        }

        if ($user->hasRole(UserRoleEnum::ROOT->value) && ! $currentUser->hasRole(UserRoleEnum::ROOT->value)) {
            return false;
        }

        return true;
    }

    public function save(): RedirectResponse|Redirector
    {
        if (! $this->canEditUser($this->user)) {
            session()->flash('error', __('You are not authorized to edit this user.'));

            return redirect()->route('admin.users.index');
        }

        $this->userEditForm->validate();
        $userService = new UserRepository;
        $userService->update($this->userEditForm->user, new UserDto(...$this->userEditForm->toUserDtoArray()));
        session()->flash('message', __('User updated successfully.'));

        return redirect()->route('admin.users.index');
    }

    public function deleteUser(): RedirectResponse|Redirector
    {
        if (! $this->user) {
            session()->flash('error', __('User not found.'));

            return redirect()->route('admin.users.index');
        }

        if (! $this->canDeleteUser($this->user)) {
            session()->flash('error', __('You are not authorized to delete this user.'));

            return redirect()->route('admin.users.index');
        }

        $userRepository = new UserRepository;
        $userRepository->delete($this->user);
        session()->flash('message', __('User deleted successfully.'));

        return redirect()->route('admin.users.index');
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.user-edit', [
            'userEditForm' => $this->userEditForm,
            'allRoles' => $this->getAllRoles(),
        ]);
    }
}
