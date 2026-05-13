<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Livewire\Forms;

use Illuminate\Database\Eloquent\Model;
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

    public ?string $phone = null;

    public ?string $date_of_birth = null;

    public ?string $civility = null;

    public ?string $bio = null;

    public ?TemporaryUploadedFile $avatar = null;

    public ?string $role = null;

    /** @var array<int, string> */
    public array $permissions = [];

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
            'password'      => $this->id === null
                ? ['required', 'string', 'min:8']
                : ['nullable', 'string', 'min:8'],
            'phone'         => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date'],
            'civility'      => ['nullable', 'string', 'max:32'],
            'bio'           => ['nullable', 'string', 'max:5000'],
            'avatar'        => ['nullable', 'image', 'max:4096'],
            'role'          => ['nullable', 'string', 'exists:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function fillFromModel(Model $user): void
    {
        $this->id            = (int) $user->getKey();
        $this->first_name    = (string) ($user->first_name ?? '');
        $this->last_name     = (string) ($user->last_name ?? '');
        $this->email         = (string) ($user->email ?? '');
        $this->password      = '';
        $this->phone         = $user->phone ?? null;
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d') ?? ($user->date_of_birth ?: null);
        $this->civility      = $user->civility ?? null;
        $this->bio           = $user->bio ?? null;
        $this->avatar        = null;

        $this->role = method_exists($user, 'getRoleNames')
            ? ($user->getRoleNames()->first() ?: null)
            : null;

        $this->permissions = method_exists($user, 'getPermissionNames')
            ? $user->getPermissionNames()->all()
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'email'         => $this->email,
            'password'      => $this->password,
            'phone'         => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'civility'      => $this->civility,
            'bio'           => $this->bio,
            'avatar'        => $this->avatar,
            'role'          => $this->role,
            'roles'         => $this->role !== null && $this->role !== '' ? [$this->role] : [],
            'permissions'   => $this->permissions,
        ];
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
