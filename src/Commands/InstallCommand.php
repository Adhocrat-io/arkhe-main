<?php

declare(strict_types=1);

namespace Arkhe\Main\Commands;

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use App\Models\User;
use Composer\Autoload\ClassLoader;
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

        if (confirm(__('arkhe::arkhe.install.patch_sidebar_prompt'), default: true)) {
            $this->installSidebar();
        }

        if (confirm(__('arkhe::arkhe.install.patch_css_prompt'), default: true)) {
            $this->installTailwindSource();
        }

        if (confirm(__('arkhe::arkhe.install.create_root'), default: true)) {
            $this->createRootUser($container, $config, $hasher);
        }

        $prefix = (string) $config->get('arkhe.admin.prefix', $config->get('arkhe.route_prefix', 'administration'));
        $this->components->info('Backend disponible sur '.url($prefix.'/users'));

        $this->components->info(__('arkhe::arkhe.install.done'));

        return self::SUCCESS;
    }

    private function createRootUser(
        Container $container,
        ConfigRepository $config,
        Hasher $hasher,
    ): void {
        $modelClass = $this->resolveUserModel($config);

        if (! $this->ensureUserModelHasTrait($modelClass)) {
            return;
        }

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
        $lastName = text(label: __('arkhe::arkhe.install.root_last_name'), required: true);

        /** @var Model $user */
        $user = new $modelClass;

        $attributes = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $hasher->make($rawPassword),
        ];

        if (Schema::hasColumn($user->getTable(), 'name')) {
            $attributes['name'] = trim($firstName.' '.$lastName);
        }

        $user->forceFill($attributes)->save();

        $user->assignRole((string) $config->get('arkhe.roles.root'));

        $this->components->info("Utilisateur root créé ({$email}).");
    }

    /**
     * Ensure the consumer's User model uses HasBackendProfile, patching the
     * file on disk if needed. The check is done by reading file contents —
     * never by introspecting the class — so that the class can be autoloaded
     * fresh (with the trait applied) later in the same command run.
     *
     * @param  class-string  $modelClass
     */
    private function ensureUserModelHasTrait(string $modelClass): bool
    {
        $file = $this->findModelFile($modelClass);

        if ($file === null) {
            $this->components->error(__('arkhe::arkhe.install.trait_missing', ['model' => $modelClass]));

            return false;
        }

        $content = @file_get_contents($file);
        if (! is_string($content) || $content === '') {
            $this->components->error(__('arkhe::arkhe.install.trait_missing', ['model' => $modelClass]));

            return false;
        }

        if (str_contains($content, 'HasBackendProfile')) {
            return true;
        }

        if (! confirm(__('arkhe::arkhe.install.patch_prompt', ['model' => $modelClass]), default: true)) {
            $this->components->error(__('arkhe::arkhe.install.trait_missing', ['model' => $modelClass]));

            return false;
        }

        $patch = $this->patchUserModelFile($file, $content);

        if (! $patch['ok']) {
            $this->components->error(__('arkhe::arkhe.install.patch_failed', ['reason' => $patch['reason']]));
            $this->components->error(__('arkhe::arkhe.install.trait_missing', ['model' => $modelClass]));

            return false;
        }

        $this->components->info(__('arkhe::arkhe.install.patch_done', ['file' => $file]));

        return true;
    }

    /**
     * Resolve the model's file path via Composer's autoloader without
     * triggering class loading.
     *
     * @param  class-string  $modelClass
     */
    private function findModelFile(string $modelClass): ?string
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($modelClass);
            if (is_string($file) && is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Best-effort patch of the consumer's User model. Conservative on purpose:
     * refuses to touch the file if the layout is non-standard, leaves a clear
     * reason for the user to act manually.
     *
     * @return array{ok: bool, reason?: string}
     */
    private function patchUserModelFile(string $file, string $content): array
    {
        if (! is_writable($file)) {
            return ['ok' => false, 'reason' => "Not writable: {$file}"];
        }

        // Refuse to auto-patch if HasRoles is present anywhere — would conflict
        // with the HasRoles already wrapped by HasBackendProfile.
        if (preg_match('/\bHasRoles\b/', $content) === 1) {
            return ['ok' => false, 'reason' => 'Model already uses Spatie\\Permission\\Traits\\HasRoles — remove it manually then add `use HasBackendProfile;`.'];
        }

        $importLine = 'use Arkhe\\Main\\Concerns\\HasBackendProfile;';

        // 1) Insert the import after the last top-level `use X;` (or after the namespace declaration).
        if (preg_match_all('/^use\s+[^;]+;[\t ]*$/m', $content, $matches, PREG_OFFSET_CAPTURE) === false || $matches[0] === []) {
            if (preg_match('/^namespace\s+[^;]+;[\t ]*$/m', $content, $nsMatch, PREG_OFFSET_CAPTURE) !== 1) {
                return ['ok' => false, 'reason' => 'Could not locate a namespace or use block to insert the import'];
            }
            $insertAt = $nsMatch[0][1] + strlen($nsMatch[0][0]);
            $content = substr($content, 0, $insertAt)."\n\n".$importLine.substr($content, $insertAt);
        } else {
            /** @var array<int, array{0: string, 1: int}> $hits */
            $hits = $matches[0];
            $lastUse = end($hits);
            $insertAt = $lastUse[1] + strlen($lastUse[0]);
            $content = substr($content, 0, $insertAt)."\n".$importLine.substr($content, $insertAt);
        }

        // 2) Insert `use HasBackendProfile;` right after the opening class brace.
        if (preg_match('/(class\s+\w+[^{]*\{)/', $content, $classMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return ['ok' => false, 'reason' => 'Could not locate the class opening brace'];
        }
        $bracePos = $classMatch[1][1] + strlen($classMatch[1][0]);
        $content = substr($content, 0, $bracePos)."\n    use HasBackendProfile;\n".substr($content, $bracePos);

        if (file_put_contents($file, $content) === false) {
            return ['ok' => false, 'reason' => "Failed to write {$file}"];
        }

        return ['ok' => true];
    }

    private function installSidebar(): void
    {
        $file = $this->locateSidebarFile();

        if ($file === null) {
            $this->components->warn(__('arkhe::arkhe.install.patch_sidebar_failed', [
                'reason' => 'No sidebar.blade.php containing <flux:sidebar.nav> found under resources/views/.',
            ]));

            return;
        }

        $result = $this->patchSidebarFile($file);

        if ($result['status'] === 'patched') {
            $this->components->info(__('arkhe::arkhe.install.patch_sidebar_done', ['file' => $result['file']]));

            return;
        }

        if ($result['status'] === 'already') {
            $this->components->info(__('arkhe::arkhe.install.patch_sidebar_already', ['file' => $result['file']]));

            return;
        }

        $this->components->warn(__('arkhe::arkhe.install.patch_sidebar_failed', [
            'reason' => $result['reason'] ?? 'unknown',
        ]));
    }

    /**
     * Locate the consumer's sidebar Blade file. Returns null if no plausible
     * candidate is found. Prefers the Livewire starter kit / Flux convention,
     * then falls back to globbing for any sidebar*.blade.php containing
     * <flux:sidebar.nav>.
     *
     * The $resourcesPath argument exists for tests; in real use it defaults
     * to the app's resource_path().
     */
    private function locateSidebarFile(?string $resourcesPath = null): ?string
    {
        $resourcesPath ??= resource_path();

        $primary = $resourcesPath.'/views/layouts/app/sidebar.blade.php';
        if (is_file($primary) && $this->fileContainsSidebarNav($primary)) {
            return $primary;
        }

        $matches = [];

        $views = $resourcesPath.'/views';
        if (! is_dir($views)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($views, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if (! $entry->isFile()) {
                continue;
            }
            $name = $entry->getFilename();
            if (! str_contains($name, 'sidebar') || ! str_ends_with($name, '.blade.php')) {
                continue;
            }
            $path = $entry->getPathname();
            // Skip the package's own published partial.
            if (str_contains($path, 'vendor/arkhe') || str_contains($path, 'vendor'.DIRECTORY_SEPARATOR.'arkhe')) {
                continue;
            }
            if ($this->fileContainsSidebarNav($path)) {
                $matches[] = $path;
            }
        }

        // Only auto-patch when exactly one candidate exists — if multiple
        // layouts have a sidebar, the user picks manually.
        return count($matches) === 1 ? $matches[0] : null;
    }

    private function fileContainsSidebarNav(string $file): bool
    {
        $content = @file_get_contents($file);

        return is_string($content) && str_contains($content, '<flux:sidebar.nav');
    }

    /**
     * Inject `@include('arkhe::partials.sidebar-items')` just before the first
     * closing </flux:sidebar.nav> tag. Idempotent.
     *
     * @return array{status: 'patched'|'already'|'failed', file?: string, reason?: string}
     */
    private function patchSidebarFile(string $file): array
    {
        if (! is_file($file)) {
            return ['status' => 'failed', 'reason' => "File not found: {$file}"];
        }
        if (! is_writable($file)) {
            return ['status' => 'failed', 'reason' => "Not writable: {$file}"];
        }

        $content = (string) file_get_contents($file);
        if ($content === '') {
            return ['status' => 'failed', 'reason' => "Empty or unreadable: {$file}"];
        }

        if (str_contains($content, 'arkhe::partials.sidebar-items')) {
            return ['status' => 'already', 'file' => $file];
        }

        $needle = '</flux:sidebar.nav>';
        $pos = strpos($content, $needle);
        if ($pos === false) {
            return ['status' => 'failed', 'reason' => "Could not find </flux:sidebar.nav> in {$file}"];
        }

        // Preserve indentation: copy the indent of the line that holds the closing tag.
        $lineStart = strrpos(substr($content, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $indent = substr($content, $lineStart, $pos - $lineStart);
        // The closing tag's indent is one step out from its children. Add
        // 4 spaces if the indent is purely whitespace (typical case).
        $childIndent = preg_match('/^\s*$/', $indent) === 1 ? $indent.'    ' : $indent;

        $injection = "\n".$childIndent."@include('arkhe::partials.sidebar-items')\n".$indent;

        $patched = substr($content, 0, $pos)
            .$injection
            .substr($content, $pos);

        if (file_put_contents($file, $patched) === false) {
            return ['status' => 'failed', 'reason' => "Failed to write {$file}"];
        }

        return ['status' => 'patched', 'file' => $file];
    }

    /**
     * Add `@source '...'` to the consumer's Tailwind v4 app.css so the build
     * scans Arkhe's published/vendor Blade views. For Tailwind v3 setups
     * (those use `@tailwind base` and rely on tailwind.config.js content
     * globs), print a manual instruction instead — patching JS is too brittle.
     */
    private function installTailwindSource(?string $resourcesPath = null, ?string $packageViewsPath = null): void
    {
        $resourcesPath ??= resource_path();
        $packageViewsPath ??= realpath(dirname(__DIR__, 2).'/resources/views') ?: null;

        if ($packageViewsPath === null) {
            $this->components->warn(__('arkhe::arkhe.install.patch_css_failed', [
                'reason' => 'Package views directory not found on disk.',
            ]));

            return;
        }

        $css = $this->locateTailwindCssFile($resourcesPath);

        if ($css === null) {
            $this->components->warn(__('arkhe::arkhe.install.patch_css_v3_manual', [
                'snippet' => "@source '../../vendor/adhocrat-io/arkhe-main/resources/views/**/*.blade.php';",
            ]));

            return;
        }

        $result = $this->patchTailwindCssFile($css, $packageViewsPath);

        if ($result['status'] === 'patched') {
            $this->components->info(__('arkhe::arkhe.install.patch_css_done', ['file' => $result['file']]));

            return;
        }

        if ($result['status'] === 'already') {
            $this->components->info(__('arkhe::arkhe.install.patch_css_already', ['file' => $result['file']]));

            return;
        }

        $this->components->warn(__('arkhe::arkhe.install.patch_css_failed', [
            'reason' => $result['reason'] ?? 'unknown',
        ]));
    }

    /**
     * Find a Tailwind v4 entrypoint under resources/css. Prefers app.css,
     * then any other .css file containing an `@import "tailwindcss"` line.
     */
    private function locateTailwindCssFile(string $resourcesPath): ?string
    {
        $primary = $resourcesPath.'/css/app.css';
        if (is_file($primary) && $this->fileImportsTailwind($primary)) {
            return $primary;
        }

        $cssDir = $resourcesPath.'/css';
        if (! is_dir($cssDir)) {
            return null;
        }

        foreach (glob($cssDir.'/*.css') ?: [] as $candidate) {
            if ($this->fileImportsTailwind($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function fileImportsTailwind(string $file): bool
    {
        $content = @file_get_contents($file);

        return is_string($content)
            && preg_match('/^\s*@import\s+["\']tailwindcss["\']/m', $content) === 1;
    }

    /**
     * @return array{status: 'patched'|'already'|'failed', file?: string, reason?: string}
     */
    private function patchTailwindCssFile(string $file, string $packageViewsPath): array
    {
        if (! is_writable($file)) {
            return ['status' => 'failed', 'reason' => "Not writable: {$file}"];
        }

        $content = (string) file_get_contents($file);
        if ($content === '') {
            return ['status' => 'failed', 'reason' => "Empty or unreadable: {$file}"];
        }

        $relative = $this->relativePath(dirname($file), $packageViewsPath).'/**/*.blade.php';
        $sourceLine = "@source '".$relative."';";

        // Idempotent: skip if the path is already sourced.
        if (preg_match('/@source\s+["\'].*adhocrat-io\/arkhe-main\/resources\/views.*["\']/', $content) === 1
            || str_contains($content, $sourceLine)
        ) {
            return ['status' => 'already', 'file' => $file];
        }

        // Prefer inserting after the last existing `@source` directive.
        if (preg_match_all('/^@source\s+[^;]+;[\t ]*$/m', $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
            /** @var array<int, array{0: string, 1: int}> $hits */
            $hits = $matches[0];
            $last = end($hits);
            $insertAt = $last[1] + strlen($last[0]);
            $patched = substr($content, 0, $insertAt)."\n".$sourceLine.substr($content, $insertAt);
        } elseif (preg_match('/^@import\s+["\']tailwindcss["\'][^;]*;[\t ]*$/m', $content, $importMatch, PREG_OFFSET_CAPTURE) === 1) {
            $insertAt = $importMatch[0][1] + strlen($importMatch[0][0]);
            $patched = substr($content, 0, $insertAt)."\n\n".$sourceLine.substr($content, $insertAt);
        } else {
            return ['status' => 'failed', 'reason' => "Could not find a @source or @import 'tailwindcss' anchor in {$file}"];
        }

        if (file_put_contents($file, $patched) === false) {
            return ['status' => 'failed', 'reason' => "Failed to write {$file}"];
        }

        return ['status' => 'patched', 'file' => $file];
    }

    /**
     * Compute a relative path from $from (a directory) to $to (any path).
     * Both should be absolute. Returns a POSIX-style result.
     */
    private function relativePath(string $from, string $to): string
    {
        $from = explode('/', trim(str_replace('\\', '/', $from), '/'));
        $to = explode('/', trim(str_replace('\\', '/', $to), '/'));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $up = $from === [] ? '.' : str_repeat('../', count($from) - 1).'..';

        return $to === [] ? $up : $up.'/'.implode('/', $to);
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
        $default = $config->get('auth.providers.users.model', User::class);

        return $default;
    }
}
