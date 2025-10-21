<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Forms\Admin\Users;

use Livewire\Form;
use Spatie\Permission\Models\Role;

class RoleEditForm extends Form
{
    public ?Role $role = null;

    public string $label = '';

    public string $name = '';

    public string $guard_name = 'web';

    public array $permissions = [];

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => __('The label is required.'),
            'label.string' => __('The label must be a string.'),
            'label.max' => __('The label must be less than 255 characters.'),
            'name.required' => __('The name is required.'),
            'guard_name.nullable' => __('The guard name is nullable.'),
            'guard_name.string' => __('The guard name must be a string.'),
            'guard_name.max' => __('The guard name must be less than 255 characters.'),
            'permissions.nullable' => __('The permissions are nullable.'),
            'permissions.array' => __('The permissions must be an array.'),
            'permissions.*.nullable' => __('The permission must be nullable.'),
            'permissions.*.integer' => __('The permission must be an integer.'),
        ];
    }

    public function setRole(Role $role): void
    {
        $this->label = $role->label;
        $this->role = $role;
        $this->name = $role->name;
        $this->guard_name = $role->guard_name;

        foreach ($role->permissions as $permission) {
            $this->permissions[$permission->id] = true;
        }
    }
}
