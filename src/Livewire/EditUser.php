<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Livewire\Forms\UserForm;
use Arkhe\Main\Services\UserService;
use Arkhe\Main\Support\RoleHierarchy;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

/**
 * A user's detail page: creation when no identifier is passed, edition
 * otherwise. Both share the same form and the same rules — splitting them
 * into two components would duplicate the validation, the avatar upload and
 * the hierarchy guards for no gain.
 */
class EditUser extends Component
{
    use WithFileUploads;

    public UserForm $userForm;

    /**
     * The user being edited, or null on creation. Carried by the route.
     *
     * Locked: it says *who* is being edited, and `save()` uses it to choose
     * between creating and updating. `save()` already re-checks the hierarchy
     * against the reloaded model, but the day a public method reads this
     * property without that precaution, the hole would open silently.
     */
    #[Locked]
    public ?int $userId = null;

    #[Locked]
    public ?string $currentAvatarUrl = null;

    public function mount(?int $user = null, ?UserRepositoryInterface $repository = null): void
    {
        $repository ??= app(UserRepositoryInterface::class);

        if ($user === null) {
            $this->authorize('create-user');

            return;
        }

        $this->authorize('update-user');

        $model = $repository->find($user);
        if ($model === null) {
            abort(404);
        }

        // An actor does not edit an account that outranks them: the list
        // already hides the action, the route must refuse it too.
        if (! RoleHierarchy::canManage(Auth::user(), $model)) {
            abort(403);
        }

        $this->userId = (int) $model->getKey();
        $this->userForm->fillFromModel($model);
        $this->currentAvatarUrl = $model->avatar_url ?? null;
    }

    public function isCreating(): bool
    {
        return $this->userId === null;
    }

    /**
     * Marks the stored picture for removal. Nothing is deleted before saving:
     * you can change your mind, and a picture dropped in the meantime cancels
     * the removal on its own.
     */
    public function markRemoveAvatar(): void
    {
        $this->authorize($this->isCreating() ? 'create-user' : 'update-user');

        $this->userForm->removeAvatar = true;
        $this->userForm->avatar = null;
    }

    public function cancelRemoveAvatar(): void
    {
        $this->authorize($this->isCreating() ? 'create-user' : 'update-user');

        $this->userForm->removeAvatar = false;
    }

    public function save(UserRepositoryInterface $repository, UserService $service): Redirector|RedirectResponse|null
    {
        $this->authorize($this->isCreating() ? 'create-user' : 'update-user');

        $this->userForm->id = $this->userId;

        $data = $this->userForm->validate();
        // validate() only returns the rule keys; pass the full form back to
        // carry the avatar and the roles along.
        $payload = array_merge($data, $this->userForm->toArray());

        $payload = $this->beforeSave($payload);

        if ($this->isCreating()) {
            $user = $service->create($payload);
            $this->afterCreate($user, $payload);

            Flux::toast(variant: 'success', text: __('arkhe::arkhe.users.created'));

            return $this->redirect(route('arkhe.users.index'), navigate: true);
        }

        $existing = $repository->find((int) $this->userId);
        if ($existing === null) {
            abort(404);
        }

        if (! RoleHierarchy::canManage(Auth::user(), $existing)) {
            abort(403);
        }

        $user = $service->update($existing, $payload);
        $this->afterUpdate($user, $payload);

        Flux::toast(variant: 'success', text: __('arkhe::arkhe.users.updated'));

        return $this->redirect(route('arkhe.users.index'), navigate: true);
    }

    // ─── Extensibility hooks ─────────────────────────────────────────────
    // Empty by default; override them in a subclass declared via
    // `config('arkhe.components.edit-user')` to plug in host-app behaviour
    // (newsletter sync, audit log, custom field) without forking the
    // component.

    /**
     * Called after validation, right before the service layer. Returns the
     * payload forwarded to `UserService::create|update`.
     *
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
    protected function afterCreate(Model $user, array $payload): void {}

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterUpdate(Model $user, array $payload): void {}

    public function render(): View
    {
        $assignable = RoleHierarchy::rolesAssignableBy(Auth::user());

        return view('arkhe::livewire.edit-user', [
            'assignableRoles' => Role::query()
                ->orderBy('name')
                ->pluck('name')
                ->filter(fn (string $name): bool => in_array($name, $assignable, true))
                ->values(),
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
