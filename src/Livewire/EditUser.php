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
 * Fiche d'un utilisateur : création quand aucun identifiant n'est passé,
 * édition sinon. Les deux partagent le même formulaire et les mêmes règles —
 * les séparer en deux composants dupliquerait la validation, l'upload d'avatar
 * et les garde-fous de hiérarchie pour un gain nul.
 */
class EditUser extends Component
{
    use WithFileUploads;

    public UserForm $userForm;

    /**
     * L'utilisateur édité, ou null en création. Porté par la route.
     *
     * Verrouillée : elle dit *qui* on édite, et `save()` s'en sert pour
     * choisir entre créer et mettre à jour. `save()` revérifie déjà la
     * hiérarchie sur le modèle rechargé, mais le jour où une méthode publique
     * lira cette propriété sans cette précaution, la faille s'ouvrirait sans
     * bruit.
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

        // Un acteur ne modifie pas un compte qui le surclasse : la liste masque
        // déjà l'action, la route doit la refuser aussi.
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

    public function save(UserRepositoryInterface $repository, UserService $service): Redirector|RedirectResponse|null
    {
        $this->authorize($this->isCreating() ? 'create-user' : 'update-user');

        $this->userForm->id = $this->userId;

        $data = $this->userForm->validate();
        // validate() ne rend que les clés des règles ; on repasse le formulaire
        // complet pour embarquer l'avatar et les rôles.
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
    // Vides par défaut ; surchargez-les dans une sous-classe déclarée via
    // `config('arkhe.components.edit-user')` pour brancher un comportement
    // applicatif (synchro newsletter, journal d'audit, champ maison) sans
    // forker le composant.

    /**
     * Appelé après validation, juste avant la couche service. Retourne le
     * payload transmis à `UserService::create|update`.
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
