<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Admin\Users;

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

class UserCreate extends Component
{
    public UserEditForm $userEditForm;

    public function mount(): void
    {
        //
    }

    public function getRoles(): Collection
    {
        $currentUser = Auth::user();
        $allowedRoles = UserRoleEnum::fromUser($currentUser)->getAllowedRoles();

        return (new RoleRepository)->getAllRoles()
            ->filter(fn ($role) => in_array($role->name, $allowedRoles, true));
    }

    public function canAssignRole(string $role): bool
    {
        $currentUser = Auth::user();
        $allowedRoles = UserRoleEnum::fromUser($currentUser)->getAllowedRoles();

        return in_array($role, $allowedRoles, true);
    }

    public function save(): Redirector|RedirectResponse
    {
        $this->authorize('create', \App\Models\User::class);

        $this->userEditForm->validate();

        $requestedRole = $this->userEditForm->role;
        if ($requestedRole && ! $this->canAssignRole($requestedRole)) {
            session()->flash('error', __('You are not authorized to assign this role.'));

            return redirect()->route('admin.users.index');
        }

        $userService = new UserRepository;
        $userService->create(new UserDto(...$this->userEditForm->toUserDtoArray()));
        session()->flash('message', __('User created successfully.'));

        return redirect()->route('admin.users.index');
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.user-create', [
            'allRoles' => $this->getRoles(),
        ])->layout(config('arkhe.admin.layout', 'components.layouts.app'));
    }
}
