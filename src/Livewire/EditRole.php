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
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Spatie\Permission\Models\Role;

/**
 * Fiche d'un rôle : c'est ici que se règlent ses permissions, cochées par
 * ressource — la liste, elle, n'en donne que le nombre.
 *
 * Un rôle ne se crée pas depuis le back-office : les rôles viennent de
 * `config('arkhe.roles')` et du seeder, parce que le code s'y réfère
 * (middlewares, `isArkheRoot()`, hiérarchie). En fabriquer un depuis l'écran
 * produirait une coquille que rien ne consulte. Modifier ses permissions, en
 * revanche, a un effet immédiat.
 *
 * Un rôle canonique garde son nom et son guard verrouillés : le middleware et
 * les recherches de config s'appuient dessus. Ses permissions restent
 * modifiables, c'est tout l'objet de l'écran.
 */
class EditRole extends Component
{
    public RoleForm $roleForm;

    // Verrouillées : ces deux-là disent *quel* rôle on édite et *s'il* est
    // figé. Sans `#[Locked]`, le client les réécrit avant d'appeler `save()`
    // — on ouvre la fiche d'un rôle anodin, on pivote vers un autre, et les
    // `authorize()` n'y voient rien puisqu'ils ne portent que sur la
    // permission, jamais sur la cible.
    #[Locked]
    public ?int $roleId = null;

    #[Locked]
    public bool $isCanonical = false;

    /**
     * La signature garde `?int $role = null` pour ne pas casser un montage
     * programmatique, mais un montage sans identifiant n'a plus de sens :
     * il n'y a rien à créer ici.
     */
    public function mount(?int $role = null, ?RoleRepositoryInterface $roles = null, ?RoleService $service = null): void
    {
        if ($role === null) {
            abort(404);
        }

        $roles ??= app(RoleRepositoryInterface::class);
        $service ??= app(RoleService::class);

        $this->authorize('update-role');

        $model = $roles->find($role);
        if ($model === null) {
            abort(404);
        }

        $this->roleId = (int) $model->getKey();
        $this->isCanonical = $service->isCanonical($model->name);
        $this->roleForm->fillFromModel($model, $this->isCanonical);
    }

    /**
     * @deprecated depuis la 3.3 — un rôle ne se crée plus depuis le
     *             back-office, cette méthode retourne toujours `false`. Elle
     *             reste pour les vues publiées qui l'appellent encore ;
     *             retrait à la prochaine majeure.
     */
    public function isCreating(): bool
    {
        return false;
    }

    public function save(RoleRepositoryInterface $roles, RoleService $service): Redirector|RedirectResponse|null
    {
        $this->authorize('update-role');

        $this->roleForm->id = $this->roleId;

        $this->roleForm->validate();
        $payload = $this->beforeSave($this->roleForm->toArray());

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
        // Méthode publique, donc appelable directement avec n'importe quel
        // tableau : elle doit porter sa propre garde. L'écriture reste filtrée
        // par `assertCanGrant()`, mais rien ne justifie de laisser muter le
        // formulaire à qui n'a pas le droit d'enregistrer.
        $this->authorize('update-role');

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
     * @deprecated depuis la 3.3 — un rôle ne se crée plus depuis le
     *             back-office, ce hook n'est plus appelé. Conservé pour ne pas
     *             casser le `parent::` d'une sous-classe ; retrait à la
     *             prochaine majeure.
     *
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
