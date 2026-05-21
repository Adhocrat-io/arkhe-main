<?php

declare(strict_types=1);

namespace Arkhe\Main\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;

/**
 * Migrates a host application from V2 (Arkhe\Main 2.x) to V3 (Arkhe\Main 3.x).
 *
 * V3 keeps the V2 namespace + provider name, so PHP source code referencing
 * Arkhe\Main classes keeps working. What changes between the two majors:
 *
 *  - new top-level config keys (dashboard_route, role_permissions, components,
 *    backend_permission, root_permission, features…) — merged into the host
 *    app's config/arkhe.php without overwriting V2 entries.
 *  - Livewire component aliases shifted from `arkhe.main.livewire.admin.…`
 *    to short forms like `arkhe.list-users` — rewritten across the host app's
 *    blade files.
 *  - Spatie permission bumped from ^6 to ^7 — the command flags it but does
 *    NOT touch composer.json (consumer-driven decision).
 */
class UpgradeFromV2Command extends Command
{
    protected $signature = 'arkhe:main:upgrade-from-v2 {--dry-run : Report changes without writing anything}';

    protected $description = 'Migrate a host application from Arkhe Main V2 to V3 (config merge + blade rewrites).';

    private const ALIAS_MAP = [
        'arkhe.main.livewire.admin.users.users-list'         => 'arkhe.list-users',
        'arkhe.main.livewire.admin.users.users-create'       => 'arkhe.list-users',
        'arkhe.main.livewire.admin.users.users-edit'         => 'arkhe.list-users',
        'arkhe.main.livewire.admin.users.roles.roles-list'   => 'arkhe.list-roles',
        'arkhe.main.livewire.admin.users.roles.role-edit'    => 'arkhe.list-roles',
    ];

    public function handle(Filesystem $files): int
    {
        $this->components->info('Arkhe Main — V2 → V3 upgrade');

        $dryRun = (bool) $this->option('dry-run');
        $report = [];

        $report[] = $this->upgradeConfig($files, $dryRun);
        $report[] = $this->rewriteBladeAliases($files, $dryRun);
        $report[] = $this->checkSpatieMajor($files);

        $this->newLine();
        $this->components->info('Summary');
        foreach (array_filter($report) as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->components->warn('Manual steps remaining:');
        $this->line('  • Run: php artisan migrate    (the profile-columns migration is idempotent)');
        $this->line('  • Run: php artisan db:seed --class=\\Arkhe\\Main\\Database\\Seeders\\ArkheRolesSeeder');
        $this->line('  • If you customised Livewire pages, ensure your overrides extend the V3 classes');
        $this->line('    (Arkhe\\Main\\Livewire\\ListUsers, ListRoles, ListPermissions, Dashboard).');

        return self::SUCCESS;
    }

    /**
     * Append V3-specific keys to the host app's config/arkhe.php, leaving the
     * existing V2 structure (admin.*, permissions, roles, role_hierarchy,
     * role_labels) untouched. Returns a human-readable status line.
     */
    private function upgradeConfig(Filesystem $files, bool $dryRun): string
    {
        $path = config_path('arkhe.php');
        if (! $files->exists($path)) {
            return 'config: skipped (no config/arkhe.php — run arkhe:main:install first)';
        }

        $contents = (string) $files->get($path);
        $missing = $this->detectMissingKeys($contents);
        if ($missing === []) {
            return 'config: already aligned with V3 — nothing to add';
        }

        if (! confirm(
            label: 'Append '.count($missing).' missing V3 key'.(count($missing) > 1 ? 's' : '').' to config/arkhe.php?',
            default: true,
        )) {
            return 'config: skipped by user';
        }

        $patch = $this->renderConfigPatch($missing);
        $next = $this->insertBeforeClosingBracket($contents, $patch);

        if ($dryRun) {
            $this->newLine();
            $this->line($patch);
            return 'config: '.count($missing).' key(s) would be added (dry run)';
        }

        $files->put($path, $next);
        return 'config: appended '.count($missing).' key(s) — '.implode(', ', array_keys($missing));
    }

    /**
     * Rewrite V2 Livewire component aliases to their V3 equivalents across
     * resources/views/. Returns a human-readable status line.
     */
    private function rewriteBladeAliases(Filesystem $files, bool $dryRun): string
    {
        $root = resource_path('views');
        if (! $files->isDirectory($root)) {
            return 'blade: skipped (no resources/views directory)';
        }

        $touched = [];
        foreach ($files->allFiles($root) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $before = (string) $files->get($file->getPathname());
            $after = strtr($before, self::ALIAS_MAP);
            if ($after === $before) {
                continue;
            }

            $touched[] = str_replace($root.'/', '', $file->getPathname());

            if (! $dryRun) {
                $files->put($file->getPathname(), $after);
            }
        }

        if ($touched === []) {
            return 'blade: no V2 aliases found';
        }

        return 'blade: '.($dryRun ? 'would rewrite' : 'rewrote').' '.count($touched).' file(s) ('.implode(', ', array_slice($touched, 0, 3)).(count($touched) > 3 ? ', …' : '').')';
    }

    /**
     * Inspect the host app's composer.json for spatie/laravel-permission and
     * warn when it is still pinned to ^6 (V3 requires ^7). Read-only.
     */
    private function checkSpatieMajor(Filesystem $files): string
    {
        $path = base_path('composer.json');
        if (! $files->exists($path)) {
            return '';
        }
        $json = json_decode((string) $files->get($path), true);
        $constraint = $json['require']['spatie/laravel-permission'] ?? null;
        if ($constraint === null) {
            return 'spatie: not declared directly (inherited from arkhe-main) — fine';
        }
        if (preg_match('/[^0-9]6\./', $constraint) || trim($constraint) === '^6') {
            return 'spatie: WARNING — composer.json pins ^6, V3 requires ^7. Bump to "^7.0".';
        }
        return 'spatie: '.$constraint.' — OK';
    }

    /**
     * @return array<string,string> keys = config slot, values = PHP literal to append
     */
    private function detectMissingKeys(string $contents): array
    {
        $candidates = [
            'dashboard_route'          => "'dashboard_route' => env('ARKHE_DASHBOARD_ROUTE'),",
            'dashboard_route_name'     => "'dashboard_route_name' => env('ARKHE_DASHBOARD_ROUTE_NAME', 'arkhe.dashboard'),",
            'override_fortify_redirect'=> "'override_fortify_redirect' => env('ARKHE_OVERRIDE_FORTIFY_REDIRECT', true),",
            'middleware'               => "'middleware' => ['web', 'auth', 'arkhe.backend'],",
            'avatar_disk'              => "'avatar_disk' => env('ARKHE_AVATAR_DISK', 'public'),",
            'avatar_path'              => "'avatar_path' => env('ARKHE_AVATAR_PATH', 'avatars'),",
            'per_page'                 => "'per_page' => 15,",
            'user_model'               => "'user_model' => null,",
            'role_permissions'         => "'role_permissions' => [\n        'root' => ['*'],\n    ],",
            'backend_permission'       => "'backend_permission' => 'access-backend',",
            'root_permission'          => "'root_permission' => 'manage-roles',",
            'components'               => "'components' => [\n        'list-users'       => \\Arkhe\\Main\\Livewire\\ListUsers::class,\n        'list-roles'       => \\Arkhe\\Main\\Livewire\\ListRoles::class,\n        'list-permissions' => \\Arkhe\\Main\\Livewire\\ListPermissions::class,\n        'dashboard'        => \\Arkhe\\Main\\Livewire\\Dashboard::class,\n    ],",
            'features'                 => "'features' => [\n        'cookie_consent' => false,\n        'seo' => false,\n    ],",
        ];

        $missing = [];
        foreach ($candidates as $key => $literal) {
            if (! preg_match("/'".preg_quote($key, '/')."'\s*=>/m", $contents)) {
                $missing[$key] = $literal;
            }
        }
        return $missing;
    }

    private function renderConfigPatch(array $missing): string
    {
        $lines = ['', '    // ── V3 additions (appended by arkhe:main:upgrade-from-v2) ──'];
        foreach ($missing as $literal) {
            $indented = '    '.str_replace("\n", "\n    ", $literal);
            $lines[] = $indented;
        }
        return implode("\n", $lines)."\n";
    }

    /**
     * Insert the patch right before the final `];` that closes the config
     * array. Falls back to appending at EOF when the trailing bracket can't
     * be located confidently.
     */
    private function insertBeforeClosingBracket(string $contents, string $patch): string
    {
        if (preg_match('/^\];\s*$/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $lastOffset = 0;
            $offset = 0;
            while (preg_match('/^\];\s*$/m', $contents, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $lastOffset = $m[0][1];
                $offset = $m[0][1] + strlen($m[0][0]);
            }
            return substr($contents, 0, $lastOffset).$patch.substr($contents, $lastOffset);
        }
        return rtrim($contents, "\n")."\n".$patch."\n";
    }
}
