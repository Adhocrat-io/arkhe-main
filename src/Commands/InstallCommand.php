<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Commands;

use Adhocrat\Arkhe\Database\Seeders\ArkheRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'arkhe:main:install';

    protected $description = 'Install Arkhe Main: publish config, run migrations, seed roles and create a root user.';

    public function handle(
        Container $container,
        ConfigRepository $config,
        Hasher $hasher,
    ): int {
        $this->components->info(__('arkhe::arkhe.install.intro'));

        if (confirm(__('arkhe::arkhe.install.publish_config'), default: true)) {
            $this->call('vendor:publish', ['--tag' => 'arkhe-config']);
        }

        if (confirm(__('arkhe::arkhe.install.publish_migrations'), default: true)) {
            $this->call('vendor:publish', ['--tag' => 'arkhe-migrations']);
        }

        if (! Schema::hasTable('roles') && confirm(__('arkhe::arkhe.install.publish_permission'), default: true)) {
            $this->call('vendor:publish', ['--provider' => 'Spatie\\Permission\\PermissionServiceProvider']);
        }

        if (confirm(__('arkhe::arkhe.install.publish_views'), default: false)) {
            $this->call('vendor:publish', ['--tag' => 'arkhe-views']);
        }

        if (confirm(__('arkhe::arkhe.install.run_migrate'), default: true)) {
            $this->call('migrate');
        }

        if (! Schema::hasTable('roles')) {
            $this->components->error(__('arkhe::arkhe.install.permission_missing'));

            return self::FAILURE;
        }

        $seeder = $container->make(ArkheRolesSeeder::class);
        $seeder->setCommand($this);
        $seeder->run();

        $this->components->info('Roles seeded.');

        if (confirm(__('arkhe::arkhe.install.create_root'), default: true)) {
            $this->createRootUser($container, $config, $hasher);
        }

        $prefix = (string) $config->get('arkhe.route_prefix', 'administration');
        $this->components->info('Backend disponible sur '.url($prefix.'/users'));

        $this->components->info(__('arkhe::arkhe.install.done'));

        return self::SUCCESS;
    }

    private function createRootUser(
        Container $container,
        ConfigRepository $config,
        Hasher $hasher,
    ): void {
        $email = text(
            label: __('arkhe::arkhe.install.root_email'),
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Email invalide.',
        );

        $rawPassword = password(
            label: __('arkhe::arkhe.install.root_password'),
            required: true,
            validate: fn (string $value) => strlen($value) >= 8 ? null : 'Le mot de passe doit faire au moins 8 caractères.',
        );

        $confirmation = password(
            label: __('arkhe::arkhe.install.root_password_conf'),
            required: true,
        );

        if ($rawPassword !== $confirmation) {
            $this->components->error('Les mots de passe ne correspondent pas. Création annulée.');

            return;
        }

        $firstName = text(label: __('arkhe::arkhe.install.root_first_name'), required: true);
        $lastName  = text(label: __('arkhe::arkhe.install.root_last_name'), required: true);

        $modelClass = $this->resolveUserModel($config);

        if (! $this->userModelHasRolesTrait($modelClass)) {
            $this->components->error(__('arkhe::arkhe.install.trait_missing', ['model' => $modelClass]));

            return;
        }

        /** @var Model $user */
        $user = new $modelClass();

        $attributes = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => $hasher->make($rawPassword),
        ];

        if (Schema::hasColumn($user->getTable(), 'name')) {
            $attributes['name'] = trim($firstName.' '.$lastName);
        }

        $user->forceFill($attributes)->save();

        $user->assignRole((string) $config->get('arkhe.roles.root'));

        $this->components->info("Utilisateur root créé ({$email}).");
    }

    /**
     * @param  class-string  $modelClass
     */
    private function userModelHasRolesTrait(string $modelClass): bool
    {
        return method_exists($modelClass, 'assignRole')
            || in_array(\Spatie\Permission\Traits\HasRoles::class, class_uses_recursive($modelClass), true);
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
}
