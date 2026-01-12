<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Admin\Users\Roles;

use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Repositories\RoleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Spatie\Permission\Models\Role;

class RolesList extends Component
{
    public function getRoles(): LengthAwarePaginator
    {
        return (new RoleRepository)->getRolesPaginated();
    }

    public function canEditRole(Role $role): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser->hasRole(UserRoleEnum::ROOT->value)) {
            return false;
        }

        return true;
    }

    public function canDeleteRole(Role $role): bool
    {
        $currentUser = Auth::user();

        if (! $currentUser->hasRole(UserRoleEnum::ROOT->value)) {
            return false;
        }

        $roleRepository = new RoleRepository;
        if ($roleRepository->isProtectedRole($role)) {
            return false;
        }

        return true;
    }

    public function createRole(): RedirectResponse|Redirector
    {
        return redirect()->route('admin.users.roles.edit');
    }

    public function editRole(Role $role): RedirectResponse|Redirector
    {
        return redirect()->route('admin.users.roles.edit', $role->id);
    }

    public function deleteRole(Role $role): RedirectResponse|Redirector
    {
        if (! $this->canDeleteRole($role)) {
            session()->flash('error', __('You are not authorized to delete this role.'));

            return redirect()->route('admin.users.roles.index');
        }

        try {
            (new RoleRepository)->delete($role);
            session()->flash('message', __('Role deleted successfully.'));
        } catch (\Exception $e) {
            Log::error('Role deletion error', ['error' => $e->getMessage(), 'role_id' => $role->id]);
            session()->flash('error', __('An error occurred while deleting the role.'));
        }

        return redirect()->route('admin.users.roles.index');
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.roles.roles-list', [
            'roles' => $this->getRoles(),
        ]);
    }
}
