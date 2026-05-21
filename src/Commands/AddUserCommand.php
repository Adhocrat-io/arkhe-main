<?php

declare(strict_types=1);

namespace Arkhe\Main\Commands;

use Arkhe\Main\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class AddUserCommand extends Command
{
    protected $signature  = 'arkhe:main:add-user
                             {--email=    : Email of the new user}
                             {--first=    : First name}
                             {--last=     : Last name}
                             {--role=     : Role to assign}
                             {--password= : Password (avoid passing on the CLI; will prompt if omitted)}';

    protected $description = 'Create a new Arkhe user from the command line.';

    public function handle(UserService $service, ConfigRepository $config): int
    {
        $modelClass = $this->resolveUserModel($config);

        if (! $this->modelUsesHasRoles($modelClass)) {
            $this->components->error(__('arkhe::arkhe.install.trait_missing', ['model' => $modelClass]));

            return self::FAILURE;
        }

        $email = $this->option('email') ?: text(
            label: 'Email',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email invalide.',
        );

        $firstName = $this->option('first') ?: text(label: 'First name', required: true);
        $lastName  = $this->option('last')  ?: text(label: 'Last name',  required: true);

        $availableRoles = Role::query()->orderBy('name')->pluck('name')->all();
        if ($availableRoles === []) {
            $this->components->error('No roles found. Run php artisan arkhe:main:install first.');

            return self::FAILURE;
        }

        $role = $this->option('role');
        if ($role === null || $role === '') {
            $role = select(
                label: 'Role',
                options: array_combine($availableRoles, $availableRoles),
                default: (string) $config->get('arkhe.roles.user'),
            );
        }

        if (! in_array($role, $availableRoles, true)) {
            $this->components->error("Unknown role [{$role}]. Available: ".implode(', ', $availableRoles));

            return self::FAILURE;
        }

        $rawPassword = $this->option('password');
        if ($rawPassword === null || $rawPassword === '') {
            $rawPassword = password(
                label: 'Password',
                required: true,
                validate: fn (string $v) => strlen($v) >= 8 ? null : 'Le mot de passe doit faire au moins 8 caractères.',
            );

            $confirm = password(label: 'Confirm password', required: true);
            if ($rawPassword !== $confirm) {
                $this->components->error('Les mots de passe ne correspondent pas.');

                return self::FAILURE;
            }
        }

        $user = $service->create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => $rawPassword,
            'roles'      => [$role],
        ]);

        $this->components->info("Utilisateur créé : {$user->email} (rôle: {$role}).");

        return self::SUCCESS;
    }

    /**
     * @return class-string<Model>
     */
    private function resolveUserModel(ConfigRepository $config): string
    {
        $configured = $config->get('arkhe.user_model');
        if (is_string($configured) && $configured !== '') {
            /** @var class-string<Model> $configured */
            return $configured;
        }

        /** @var class-string<Model> $default */
        $default = $config->get('auth.providers.users.model', \App\Models\User::class);

        return $default;
    }

    /**
     * @param  class-string  $modelClass
     */
    private function modelUsesHasRoles(string $modelClass): bool
    {
        return method_exists($modelClass, 'assignRole')
            || in_array(\Spatie\Permission\Traits\HasRoles::class, class_uses_recursive($modelClass), true);
    }
}
