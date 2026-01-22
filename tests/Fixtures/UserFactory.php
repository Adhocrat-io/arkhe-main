<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'civility' => fake()->randomElement(['Mr.', 'Mrs.', 'Ms.', 'Dr.']),
            'profession' => fake()->jobTitle(),
            'email' => fake()->unique()->userName().'@gmail.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            if ($user->roles()->count() === 0) {
                $user->assignRole(UserRoleEnum::SUBSCRIBER->value);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(UserRoleEnum $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            $user->syncRoles([$role->value]);
        });
    }

    public function root(): static
    {
        return $this->withRole(UserRoleEnum::ROOT);
    }

    public function admin(): static
    {
        return $this->withRole(UserRoleEnum::ADMIN);
    }

    public function editorial(): static
    {
        return $this->withRole(UserRoleEnum::EDITORIAL);
    }

    public function author(): static
    {
        return $this->withRole(UserRoleEnum::AUTHOR);
    }

    public function contributor(): static
    {
        return $this->withRole(UserRoleEnum::CONTRIBUTOR);
    }

    public function subscriber(): static
    {
        return $this->withRole(UserRoleEnum::SUBSCRIBER);
    }

    public function guest(): static
    {
        return $this->withRole(UserRoleEnum::GUEST);
    }
}
