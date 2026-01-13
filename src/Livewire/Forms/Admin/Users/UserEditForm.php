<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire\Forms\Admin\Users;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;
use Livewire\Form;

class UserEditForm extends Form
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public ?string $date_of_birth = null;

    public ?string $civility = null;

    public ?string $profession = null;

    public ?string $role = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public function mount(?User $user): void
    {
        $this->user = $user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d');
        $this->civility = $user->civility;
        $this->profession = $user->profession;

        // Charger la relation roles si elle n'est pas déjà chargée
        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        $this->role = $user->roles->first()?->name;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.($this->user?->id ?? ''), 'email:rfc,dns'],
            'date_of_birth' => ['nullable', 'date'],
            'civility' => ['nullable', 'string'],
            'profession' => ['nullable', 'string'],
            'role' => ['required', 'string'],
        ];

        if (! $this->user) {
            $rules['password'] = ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'];
            $rules['password_confirmation'] = ['required'];
        } else {
            $rules['password'] = ['nullable', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'];
            $rules['password_confirmation'] = ['nullable'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            '*.string' => __('The :attribute must be a string.'),
            '*.max' => __('The :attribute must be less than :max characters.'),
            '*.min' => __('The :attribute must be at least :min characters.'),
            '*.email' => __('The :attribute must be a valid email address.'),
            '*.unique' => __('The :attribute has already been taken.'),
            '*.required' => __('The :attribute is required.'),
            '*.confirmed' => __('The :attribute confirmation does not match.'),
        ];

        $messages['password.required'] = __('The password is required.');
        $messages['password.min'] = __('The password must be at least 8 characters long.');
        $messages['password.mixed'] = __('The password must contain at least one uppercase and one lowercase letter.');
        $messages['password.numbers'] = __('The password must contain at least one number.');
        $messages['password.symbols'] = __('The password must contain at least one symbol.');
        $messages['password.confirmed'] = __('The password confirmation does not match.');
        $messages['password_confirmation.required'] = __('The password confirmation is required.');

        return $messages;
    }

    public function toUserDtoArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'date_of_birth' => $this->date_of_birth ? Carbon::parse($this->date_of_birth) : null,
            'civility' => $this->civility,
            'profession' => $this->profession,
            'password' => $this->password,
            'role' => $this->role,
        ];
    }
}
