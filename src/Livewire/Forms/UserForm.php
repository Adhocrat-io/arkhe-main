<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Forms;

use Arkhe\Main\Support\RoleHierarchy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class UserForm extends Form
{
    public ?int $id = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public ?string $phone = null;

    public ?string $date_of_birth = null;

    public ?string $civility = null;

    public ?string $bio = null;

    public ?TemporaryUploadedFile $avatar = null;

    /**
     * Deferred removal of the stored picture: marked on screen, applied on
     * save. Without it, an uploaded picture could only be replaced, never
     * removed.
     */
    public bool $removeAvatar = false;

    public ?string $role = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userTable = $this->resolveUserTable();

        return [
            'first_name'    => ['required', 'string', 'max:120'],
            'last_name'     => ['required', 'string', 'max:120'],
            'email'         => ['required', 'email', 'max:255', Rule::unique($userTable, 'email')->ignore($this->id)],
            'password'      => $this->passwordRules(),
            'phone'         => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date'],
            'civility'      => ['nullable', 'string', 'max:32'],
            'bio'           => ['nullable', 'string', 'max:5000'],
            'avatar'                => ['nullable', 'image', 'max:4096'],
            'passwordConfirmation' => ['nullable', 'string'],
            'role'                  => ['nullable', 'string', 'exists:roles,name', $this->roleAssignableRule()],
        ];
    }

    public function fillFromModel(Model $user): void
    {
        $this->id            = (int) $user->getKey();
        $this->first_name    = (string) ($user->first_name ?? '');
        $this->last_name     = (string) ($user->last_name ?? '');
        $this->email                = (string) ($user->email ?? '');
        $this->password             = '';
        $this->passwordConfirmation = '';
        $this->phone         = $user->phone ?? null;
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d') ?? ($user->date_of_birth ?: null);
        $this->civility      = $user->civility ?? null;
        $this->bio           = $user->bio ?? null;
        $this->avatar        = null;
        $this->removeAvatar  = false;

        $this->role = method_exists($user, 'getRoleNames')
            ? ($user->getRoleNames()->first() ?: null)
            : null;
    }

    /**
     * Payload handed to `UserService`.
     *
     * ⚠️ Deliberately NOT named `toArray()`. Livewire serialises a form object
     * into the component snapshot through `toArray()`
     * (`FormObjectSynth::dehydrate`), so overriding it to mean "the fields I
     * want to persist" silently drops every other property from the snapshot —
     * they come back at their default value on the next round trip.
     *
     * That is exactly what used to happen to `passwordConfirmation`: a first
     * rejected submit (a duplicate e-mail, say) re-rendered the form with the
     * confirmation field emptied, and the next submit failed with "the password
     * confirmation does not match" although the operator had touched nothing.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'email'         => $this->email,
            'password'      => $this->password,
            // Serialized along with the rest: missing from here, Livewire
            // does not put it back into the rehydrated form, and the
            // confirmation was compared to an empty string on save.
            'passwordConfirmation' => $this->passwordConfirmation,
            'phone'         => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'civility'      => $this->civility,
            'bio'           => $this->bio,
            'avatar'        => $this->avatar,
            // The key carries the property's exact name: Livewire only
            // restores on the next round-trip what `toArray()` exposes, and a
            // snake_case alias would lose the flag between two requests — the
            // removal would never apply.
            'removeAvatar'  => $this->removeAvatar,
            'role'          => $this->role,
            'roles'         => $this->role !== null && $this->role !== '' ? [$this->role] : [],
            // No `permissions` key on purpose. Rights are granted through
            // roles from the backend, so the form neither reads nor writes a
            // user's direct permissions. It used to carry them invisibly: no
            // screen ever rendered the field, yet every save round-tripped
            // whatever the seeder had granted straight back through
            // `syncPermissions()`, which is destructive. `UserService` still
            // accepts the key for programmatic callers, guarded against
            // escalation — see UserService::assertCanGrantPermissions().
        ];
    }

    private function roleAssignableRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            /** @var Model|null $actor */
            $actor = Auth::user();

            if (! RoleHierarchy::canAssign($actor, (string) $value)) {
                $fail(__('arkhe::arkhe.validation.role_above_rank'));
            }
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function passwordRules(): array
    {
        // Creating: password is mandatory + must be confirmed.
        if ($this->id === null) {
            return ['required', 'string', 'min:8', $this->passwordConfirmedRule()];
        }

        // Editing with an empty field: keep the existing password, skip validation.
        if ($this->password === '') {
            return [];
        }

        // Editing with a new value: enforce strength + match the confirmation.
        return ['string', 'min:8', $this->passwordConfirmedRule()];
    }

    /**
     * Replaces Laravel's `confirmed` rule, which does not see the
     * confirmation in the validator's dictionary for a Form Object.
     *
     * The value is **captured now**, while the rules are being built, and not
     * read from `$this` when the closure runs: Livewire resets the form's
     * properties during `validate()`, so a late read compares the password to
     * an empty string and rejects a pair that actually matches.
     */
    private function passwordConfirmedRule(): Closure
    {
        $confirmation = (string) ($this->passwordConfirmation ?? '');

        return function (string $attribute, mixed $value, Closure $fail) use ($confirmation): void {
            if ((string) $value !== $confirmation) {
                $fail(trans('validation.confirmed', ['attribute' => $this->translateAttribute($attribute)]));
            }
        };
    }

    /**
     * Resolve an attribute name through the app's `validation.attributes`
     * dictionary, the way Laravel does for its own rules.
     *
     * A closure rule bypasses that resolution: the raw name lands in the
     * message, so a French install read "Le champ de confirmation password ne
     * correspond pas". Falls back to the humanised name when the app declares
     * no translation.
     */
    private function translateAttribute(string $attribute): string
    {
        $key = 'validation.attributes.'.$attribute;
        $translated = trans($key);

        return $translated === $key
            ? str_replace('_', ' ', Str::snake($attribute))
            : (string) $translated;
    }

    private function resolveUserTable(): string
    {
        $configured = config('arkhe.user_model');
        $class      = is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('auth.providers.users.model', \App\Models\User::class);

        /** @var Model $instance */
        $instance = new $class();

        return $instance->getTable();
    }
}
