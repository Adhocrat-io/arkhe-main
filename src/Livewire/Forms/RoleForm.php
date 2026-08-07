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

    /**
     * Informational only: no authorization decision relies on it. The
     * validation rules read the database again ({@see rules()}), and
     * `RoleService::update()` recomputes the status on its side.
     */
    public bool $is_canonical = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Canonical status is read back from the database, never from
        // `$this->is_canonical`: that property is public, so the client
        // rewrites it. Trusting it meant dropping *every* rule on the name on
        // request — no more `required`, no more maximum length, no more
        // uniqueness, and two roles could carry the same name.
        $nameRules = $this->resolveIsCanonical()
            ? [] // a canonical role is not renamed; the field is locked
            : ['required', 'string', 'max:120', Rule::unique('roles', 'name')->ignore($this->id)];

        return [
            'name'          => $nameRules,
            'guard_name'    => ['required', 'string', 'max:60'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Is the role being edited canonical, according to the database and the
     * configuration?
     */
    private function resolveIsCanonical(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $name = Role::query()->whereKey($this->id)->value('name');

        return $name !== null
            && in_array((string) $name, array_values((array) config('arkhe.roles', [])), true);
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'guard_name'  => $this->guard_name,
            'permissions' => $this->permissions,
        ];
    }
}
