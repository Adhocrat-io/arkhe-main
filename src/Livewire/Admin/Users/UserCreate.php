<?php

namespace Arkhe\Main\Livewire\Admin\Users;

use Arkhe\Main\DataTransferObjects\UserDto;
use Arkhe\Main\Livewire\Forms\Admin\Users\UserEditForm;
use Arkhe\Main\Repositories\RoleRepository;
use Arkhe\Main\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class UserCreate extends Component
{
    public UserEditForm $userEditForm;

    public function mount(): void {}

    public function getRoles(): Collection
    {
        return (new RoleRepository)->getAllRoles();
    }

    public function save(): Redirector|RedirectResponse
    {
        $this->userEditForm->validate();
        $userService = app(UserRepository::class);
        $userService->create(new UserDto(...$this->userEditForm->toUserDtoArray()));
        session()->flash('message', __('User created successfully.'));

        return redirect()->route('admin.users.index');
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.user-create', [
            'allRoles' => $this->getRoles(),
        ]);
    }
}
