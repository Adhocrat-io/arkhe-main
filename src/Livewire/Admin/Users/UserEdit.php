<?php

namespace Arkhe\Main\Livewire\Admin\Users;

use App\Models\User;
use Arkhe\Main\DataTransferObjects\UserDto;
use Arkhe\Main\Livewire\Forms\Admin\Users\UserEditForm;
use Arkhe\Main\Repositories\RoleRepository;
use Arkhe\Main\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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

    public function save(): RedirectResponse|Redirector
    {
        $this->userEditForm->validate();
        $userService = app(UserRepository::class);
        $userService->update($this->userEditForm->user, new UserDto(...$this->userEditForm->toUserDtoArray()));
        session()->flash('message', __('User updated successfully.'));

        return redirect()->route('admin.users.index');
    }

    public function deleteUser(): RedirectResponse|Redirector
    {
        $userRepository = app(UserRepository::class);

        if ($this->user) {
            $userRepository->delete($this->user);
            session()->flash('message', __('User deleted successfully.'));
        }

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
