<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Admin\Users\Roles;

use Arkhe\Main\DataTransferObjects\RoleDto;
use Arkhe\Main\Livewire\Forms\Admin\Users\RoleEditForm;
use Arkhe\Main\Repositories\RoleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        $this->allPermissions = Permission::all();

        if ($role && $role->exists) {
            $this->role = $role->load('permissions');
            $this->roleEditForm->setRole($role);
        }
    }

    public function save(): RedirectResponse|Redirector|null
    {
        if ($this->role && $this->role->exists) {
            if (Gate::denies('update', $this->role)) {
                session()->flash('error', __('You are not authorized to update this role.'));

                return redirect()->route('admin.users.roles.index');
            }
        } else {
            if (Gate::denies('create', Role::class)) {
                session()->flash('error', __('You are not authorized to create roles.'));

                return redirect()->route('admin.users.roles.index');
            }
        }

        try {
            $this->roleEditForm->validate();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Role validation error', [
                'error' => $e->getMessage(),
                'role' => $this->role?->id,
            ]);
            session()->flash('error', __('An error occurred while validating the data.'));

            return null;
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
            try {
                $roleRepository->update($this->role, $roleDto);
                session()->flash('message', __('Role updated successfully.'));
            } catch (\Exception $e) {
                Log::error('Role update error', [
                    'error' => $e->getMessage(),
                    'role_id' => $this->role->id,
                ]);
                session()->flash('error', __('An error occurred while updating the role.'));

                return null;
            }
        } else {
            try {
                $roleRepository->create($roleDto);
                session()->flash('message', __('Role created successfully.'));
            } catch (\Exception $e) {
                Log::error('Role creation error', [
                    'error' => $e->getMessage(),
                    'role_name' => $roleDto->name,
                ]);
                session()->flash('error', __('An error occurred while creating the role.'));

                return null;
            }
        }

        return redirect()->route('admin.users.roles.index');
    }

    public function deleteRole(): RedirectResponse|Redirector|null
    {
        if (! $this->role) {
            session()->flash('error', __('Role not found.'));

            return redirect()->route('admin.users.roles.index');
        }

        if (Gate::denies('delete', $this->role)) {
            session()->flash('error', __('You are not authorized to delete this role.'));

            return redirect()->route('admin.users.roles.index');
        }

        $roleRepository = new RoleRepository;

        try {
            $roleRepository->delete($this->role);
            session()->flash('message', __('Role deleted successfully.'));

            return redirect()->route('admin.users.roles.index');
        } catch (\Exception $e) {
            Log::error('Role deletion error', [
                'error' => $e->getMessage(),
                'role_id' => $this->role->id,
            ]);
            session()->flash('error', __('An error occurred while deleting the role.'));

            return null;
        }
    }

    public function render(): View
    {
        return view('arkhe-main::livewire.admin.users.roles.role-edit', [
            'role' => $this->role,
        ])->layout(config('arkhe.admin.layout', 'components.layouts.app'));
    }
}
