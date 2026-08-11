<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Concerns\RequiresStrongAuth;
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
 * A role's detail page: this is where its permissions get set, ticked per
 * resource — the list only gives their count.
 *
 * Roles are not created from the back-office: they come from
 * `config('arkhe.roles')` and the seeder, because the code refers to them
 * (middlewares, `isArkheRoot()`, hierarchy). Building one from the screen
 * would produce an empty shell nothing ever looks up. Changing its
 * permissions, on the other hand, takes effect immediately.
 *
 * A canonical role keeps its name and guard locked: the middleware and the
 * config lookups rely on them. Its permissions stay editable — that is the
 * whole point of this screen.
 */
class EditRole extends Component
{
    use RequiresStrongAuth;

    public RoleForm $roleForm;

    // Locked: these two say *which* role is being edited and *whether* it is
    // frozen. Without `#[Locked]`, the client rewrites them before calling
    // `save()` — you open a harmless role's page, pivot to another one, and
    // the `authorize()` calls see nothing since they only cover the
    // permission, never the target.
    #[Locked]
    public ?int $roleId = null;

    #[Locked]
    public bool $isCanonical = false;

    /**
     * The signature keeps `?int $role = null` so a programmatic mount does
     * not break, but mounting without an identifier no longer makes sense:
     * there is nothing to create here.
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
     * @deprecated since 4.0 — roles are no longer created from the
     *             back-office, so this method always returns `false`. It
     *             stays for published views that still call it; removal in
     *             the next major.
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
     * Ticks (or unticks) every permission of a resource in one gesture:
     * granting "all of users" to a role should not take five clicks.
     *
     * @param  array<int, string>  $permissions
     */
    public function toggleGroup(array $permissions, bool $checked): void
    {
        // Public method, so callable directly with any array: it has to carry
        // its own guard. Writes stay filtered by `assertCanGrant()`, but
        // nothing justifies letting someone who cannot save mutate the form.
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
    // Override these in a subclass declared via
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
     * @deprecated since 4.0 — roles are no longer created from the
     *             back-office, so this hook is never called. Kept so a
     *             subclass's `parent::` does not break; removal in the next
     *             major.
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
