<?php

namespace Arkhe\Main\Livewire\Admin\Users\Roles;

use Arkhe\Main\DataTransferObjects\RoleDto;
use Arkhe\Main\Livewire\Forms\Admin\Users\RoleEditForm;
use Arkhe\Main\Repositories\RoleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleEdit extends Component
{
    public ?Role $role = null;

    public RoleEditForm $roleEditForm;

    public ?Collection $allPermissions = null;

    public function mount(?Role $role): void
    {
        // TODO: changer pour un repository s'il y a plus qu'une méthode
        $this->allPermissions = Permission::all();

        if ($role->exists) {
            $this->role = $role->load('permissions');
            $this->roleEditForm->setRole($role);
        }
    }

    public function save(): RedirectResponse|Redirector|null
    {
        try {
            $this->roleEditForm->validate();
        } catch (\Exception $e) {
            session()->flash('error', __('An error occurred while validating the data.'));
            $this->addError('error', $e->getMessage());
        }

        $roleRepository = new RoleRepository;

        $permissions = array_keys(array_filter($this->roleEditForm->permissions, fn ($value) => $value === true));

        $roleDto = new RoleDto(
            $this->roleEditForm->name,
            $this->roleEditForm->label,
            $this->roleEditForm->guard_name,
            $permissions
        );

        if ($this->role && $this->role->exists) {
            $role = $roleRepository->update(
                $this->role,
                $roleDto
            );

            session()->flash('message', __('Role updated successfully.'));
        } else {
            try {
                $role = $roleRepository->create(
                    $roleDto
                );

                session()->flash('message', __('Role created successfully.'));
            } catch (\Exception $e) {
                session()->flash('error', __('An error occurred while creating the role.'));
                $this->addError('error', $e->getMessage());

                return null;
            }
        }

        return redirect()->route('admin.users.roles.index');
    }

    public function deleteRole(): RedirectResponse|Redirector
    {
        (new RoleRepository)->delete($this->role);
        session()->flash('message', __('Role deleted successfully.'));

        return redirect()->route('admin.users.roles.index');
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.roles.role-edit', [
            'role' => $this->role,
        ]);
    }
}
