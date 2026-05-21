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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'guard_name' => $this->guard_name,
        ];
    }
}
