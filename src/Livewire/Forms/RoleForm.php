<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Spatie\Permission\Models\Role;

class RoleForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $guard_name = 'web';

    /** @var array<int, string> */
    public array $permissions = [];

    public bool $is_canonical = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $nameRules = $this->is_canonical
            ? [] // canonical roles cannot be renamed; field is read-only in the UI
            : ['required', 'string', 'max:120', Rule::unique('roles', 'name')->ignore($this->id)];

        return [
            'name'          => $nameRules,
            'guard_name'    => ['required', 'string', 'max:60'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function fillFromModel(Role $role, bool $isCanonical): void
    {
        $this->id           = (int) $role->getKey();
        $this->name         = (string) $role->name;
        $this->guard_name   = (string) $role->guard_name;
        $this->permissions  = $role->permissions->pluck('name')->all();
        $this->is_canonical = $isCanonical;
    }

    /**
     * Payload handed to the service. See `UserForm::toPayload()` for why this
     * must not be called `toArray()` — Livewire serialises form objects through
     * `toArray()`, so overriding it drops every unlisted property from the
     * component snapshot.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'name'        => $this->name,
            'guard_name'  => $this->guard_name,
            'permissions' => $this->permissions,
        ];
    }
}
