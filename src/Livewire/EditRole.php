<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Livewire\Forms\RoleForm;
use Arkhe\Main\Services\RoleService;
use Arkhe\Main\Support\PermissionGroups;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Spatie\Permission\Models\Role;

/**
 * Fiche d'un rôle : création quand aucun identifiant n'est passé, édition
 * sinon. C'est ici que se règlent les permissions, cochées par ressource —
 * la liste, elle, n'en donne que le nombre.
 *
 * Un rôle canonique (déclaré dans `config('arkhe.roles')`) garde son nom et
 * son guard verrouillés : le middleware et les recherches de config s'appuient
 * dessus. Ses permissions restent modifiables, c'est tout l'objet de l'écran.
 */
class EditRole extends Component
{
    public RoleForm $roleForm;

    public ?int $roleId = null;

    public bool $isCanonical = false;

    public function mount(?int $role = null, ?RoleRepositoryInterface $roles = null, ?RoleService $service = null): void
    {
        $roles ??= app(RoleRepositoryInterface::class);
        $service ??= app(RoleService::class);

        if ($role === null) {
            $this->authorize('create-role');

            return;
        }

        $this->authorize('update-role');

        $model = $roles->find($role);
        if ($model === null) {
            abort(404);
        }

        $this->roleId = (int) $model->getKey();
        $this->isCanonical = $service->isCanonical($model->name);
        $this->roleForm->fillFromModel($model, $this->isCanonical);
    }

    public function isCreating(): bool
    {
        return $this->roleId === null;
    }

    public function save(RoleRepositoryInterface $roles, RoleService $service): Redirector|RedirectResponse|null
    {
        $this->authorize($this->isCreating() ? 'create-role' : 'update-role');

        $this->roleForm->id = $this->roleId;

        $this->roleForm->validate();
        $payload = $this->beforeSave($this->roleForm->toArray());

        if ($this->isCreating()) {
            $role = $service->create($payload);
            $this->afterCreate($role, $payload);

            Flux::toast(variant: 'success', text: __('arkhe::arkhe.roles.created'));

            return $this->redirect(route('arkhe.roles.index'), navigate: true);
        }

        $existing = $roles->find((int) $this->roleId);
        if ($existing === null) {
            abort(404);
        }

        $role = $service->update($existing, $payload);
        $this->afterUpdate($role, $payload);

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.roles.updated'));

        return $this->redirect(route('arkhe.roles.index'), navigate: true);
    }

    /**
     * Coche (ou décoche) toutes les permissions d'une ressource d'un geste :
     * attribuer « tout users » à un rôle ne devrait pas demander cinq clics.
     *
     * @param  array<int, string>  $permissions
     */
    public function toggleGroup(array $permissions, bool $checked): void
    {
        $current = array_flip($this->roleForm->permissions);

        foreach ($permissions as $permission) {
            if ($checked) {
                $current[$permission] = true;
            } else {
                unset($current[$permission]);
            }
        }

        $this->roleForm->permissions = array_keys($current);
    }

    // ─── Extensibility hooks ─────────────────────────────────────────────
    // Surchargez-les dans une sous-classe déclarée via
    // `config('arkhe.components.edit-role')`.

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function beforeSave(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterCreate(Role $role, array $payload): void {}

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterUpdate(Role $role, array $payload): void {}

    public function render(PermissionRepositoryInterface $permissions): View
    {
        return view('arkhe::livewire.edit-role', [
            'permissionGroups' => PermissionGroups::build($permissions->all()->pluck('name')),
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
