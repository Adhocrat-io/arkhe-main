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
     * Informatif seulement : aucune décision d'autorisation ne s'y appuie.
     * Les règles de validation relisent la base ({@see rules()}), et
     * `RoleService::update()` recalcule le statut de son côté.
     */
    public bool $is_canonical = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Le caractère canonique est relu depuis la base, jamais depuis
        // `$this->is_canonical` : cette propriété est publique, donc réécrite
        // par le client. La croire revenait à laisser tomber *toutes* les
        // règles du nom sur simple demande — plus de `required`, plus de
        // longueur maximale, plus d'unicité, et deux rôles pouvaient porter
        // le même nom.
        $nameRules = $this->resolveIsCanonical()
            ? [] // un rôle canonique ne se renomme pas ; le champ est verrouillé
            : ['required', 'string', 'max:120', Rule::unique('roles', 'name')->ignore($this->id)];

        return [
            'name'          => $nameRules,
            'guard_name'    => ['required', 'string', 'max:60'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Le rôle édité est-il canonique, d'après la base et la configuration ?
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
