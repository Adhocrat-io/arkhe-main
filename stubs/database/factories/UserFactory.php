<?php

namespace Database\Factories;

use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'civility' => fake()->randomElement(['Mr.', 'Mrs.', 'Ms.', 'Dr.']),
            'profession' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => Str::random(10),
            'two_factor_recovery_codes' => Str::random(10),
            'two_factor_confirmed_at' => now(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            // Assign default role if user has no roles
            if ($user->roles()->count() === 0) {
                $user->assignRole(UserRoleEnum::SUBSCRIBER->value);
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model does not have two-factor authentication configured.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Assign a specific role to the user after creation.
     */
    public function withRole(UserRoleEnum $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            $user->syncRoles([$role->value]);
        });
    }

    /**
     * Create a root user.
     */
    public function root(): static
    {
        return $this->withRole(UserRoleEnum::ROOT);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->withRole(UserRoleEnum::ADMIN);
    }

    /**
     * Create an editorial user.
     */
    public function editorial(): static
    {
        return $this->withRole(UserRoleEnum::EDITORIAL);
    }

    /**
     * Create an author user.
     */
    public function author(): static
    {
        return $this->withRole(UserRoleEnum::AUTHOR);
    }

    /**
     * Create a contributor user.
     */
    public function contributor(): static
    {
        return $this->withRole(UserRoleEnum::CONTRIBUTOR);
    }

    /**
     * Create a subscriber user.
     */
    public function subscriber(): static
    {
        return $this->withRole(UserRoleEnum::SUBSCRIBER);
    }

    /**
     * Create a guest user.
     */
    public function guest(): static
    {
        return $this->withRole(UserRoleEnum::GUEST);
    }
}
