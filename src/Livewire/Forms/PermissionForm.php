<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Spatie\Permission\Models\Permission;

class PermissionForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $guard_name = 'web';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:120', Rule::unique('permissions', 'name')->ignore($this->id)],
            'guard_name' => ['required', 'string', 'max:60'],
        ];
    }

    public function fillFromModel(Permission $permission): void
    {
        $this->id         = (int) $permission->getKey();
        $this->name       = (string) $permission->name;
        $this->guard_name = (string) $permission->guard_name;
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
            'name'       => $this->name,
            'guard_name' => $this->guard_name,
        ];
    }
}
