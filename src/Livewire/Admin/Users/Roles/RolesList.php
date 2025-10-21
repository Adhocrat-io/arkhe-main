<?php

namespace Arkhe\Main\Livewire\Admin\Users\Roles;

use Arkhe\Main\Repositories\RoleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $currentUser = auth()->user();

        if (! $currentUser->hasAnyRole(['root'])) {
            return false;
        }

        return true;
    }

    public function canDeleteRole(Role $role): bool
    {
        $currentUser = auth()->user();

        if (! $currentUser->hasAnyRole(['root'])) {
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
        (new RoleRepository)->delete($role);

        return redirect()->route('admin.users.roles.index');
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.roles.roles-list', [
            'roles' => $this->getRoles(),
        ]);
    }
}
