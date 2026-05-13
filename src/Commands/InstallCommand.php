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
            if (confirm(__('arkhe::arkhe.install.patch_prompt', ['model' => $modelClass]), default: true)) {
                $patch = $this->patchUserModel($modelClass);
                if ($patch['ok']) {
                    $this->components->info(__('arkhe::arkhe.install.patch_done', ['file' => $patch['file']]));
                    $this->components->warn(__('arkhe::arkhe.install.patch_restart'));

                    return;
                }

                $this->components->error(__('arkhe::arkhe.install.patch_failed', ['reason' => $patch['reason']]));
            }

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
     * Best-effort patch of the consumer's User model. Conservative on purpose:
     * refuses to touch the file if the layout is non-standard, leaves a clear
     * reason for the user to act manually.
     *
     * @param  class-string  $modelClass
     * @return array{ok: bool, file?: string, reason?: string}
     */
    private function patchUserModel(string $modelClass): array
    {
        try {
            $reflection = new \ReflectionClass($modelClass);
        } catch (\ReflectionException) {
            return ['ok' => false, 'reason' => "Reflection failed on {$modelClass}"];
        }

        $file = $reflection->getFileName();
        if (! is_string($file) || ! is_file($file)) {
            return ['ok' => false, 'reason' => 'User model file not found on disk'];
        }
        if (! is_writable($file)) {
            return ['ok' => false, 'reason' => "Not writable: {$file}"];
        }

        $content = (string) file_get_contents($file);
        if ($content === '') {
            return ['ok' => false, 'reason' => "Empty or unreadable: {$file}"];
        }

        if (str_contains($content, 'HasBackendProfile')) {
            return ['ok' => true, 'file' => $file];
        }

        // Refuse to auto-patch if HasRoles is present anywhere — would conflict
        // with the HasRoles already wrapped by HasBackendProfile.
        if (preg_match('/\bHasRoles\b/', $content) === 1) {
            return ['ok' => false, 'reason' => 'Model already uses Spatie\\Permission\\Traits\\HasRoles — remove it manually then add `use HasBackendProfile;`.'];
        }

        $importLine = 'use Adhocrat\\Arkhe\\Concerns\\HasBackendProfile;';

        // 1) Insert the import after the last top-level `use X;` (or after the namespace declaration).
        if (preg_match_all('/^use\s+[^;]+;[\t ]*$/m', $content, $matches, PREG_OFFSET_CAPTURE) === false || $matches[0] === []) {
            if (preg_match('/^namespace\s+[^;]+;[\t ]*$/m', $content, $nsMatch, PREG_OFFSET_CAPTURE) !== 1) {
                return ['ok' => false, 'reason' => 'Could not locate a namespace or use block to insert the import'];
            }
            $insertAt = $nsMatch[0][1] + strlen($nsMatch[0][0]);
            $content  = substr($content, 0, $insertAt)."\n\n".$importLine.substr($content, $insertAt);
        } else {
            /** @var array<int, array{0: string, 1: int}> $hits */
            $hits     = $matches[0];
            $lastUse  = end($hits);
            $insertAt = $lastUse[1] + strlen($lastUse[0]);
            $content  = substr($content, 0, $insertAt)."\n".$importLine.substr($content, $insertAt);
        }

        // 2) Insert `use HasBackendProfile;` right after the opening class brace.
        if (preg_match('/(class\s+\w+[^{]*\{)/', $content, $classMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return ['ok' => false, 'reason' => 'Could not locate the class opening brace'];
        }
        $bracePos = $classMatch[1][1] + strlen($classMatch[1][0]);
        $content  = substr($content, 0, $bracePos)."\n    use HasBackendProfile;\n".substr($content, $bracePos);

        if (file_put_contents($file, $content) === false) {
            return ['ok' => false, 'reason' => "Failed to write {$file}"];
        }

        return ['ok' => true, 'file' => $file];
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
