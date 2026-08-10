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
 *  - `roles` / `permissions` config layout: V2 kept a role => permissions map
 *    in `roles` and grouped permission lists in `permissions`; V3 expects a
 *    key => name map in `roles`, a flat list in `permissions`, and the
 *    role => permissions mapping in `role_permissions`. The command rewrites
 *    the host config accordingly (the V2 `roles` body moves verbatim to
 *    `role_permissions`, so enum keys and comments survive).
 *  - new top-level config keys (role_permissions, components,
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

    /**
     * Set when a confirmed reshape adds `role_permissions`, so the key-append
     * step never proposes its default duplicate (the file is only actually
     * rewritten later — or not at all in dry-run).
     */
    private bool $reshapeAddsRolePermissions = false;

    private const ALIAS_MAP = [
        'arkhe.main.livewire.admin.users.users-list' => 'arkhe.list-users',
        'arkhe.main.livewire.admin.users.users-create' => 'arkhe.list-users',
        'arkhe.main.livewire.admin.users.users-edit' => 'arkhe.list-users',
        'arkhe.main.livewire.admin.users.roles.roles-list' => 'arkhe.list-roles',
        'arkhe.main.livewire.admin.users.roles.role-edit' => 'arkhe.list-roles',
    ];

    public function handle(Filesystem $files): int
    {
        $this->components->info('Arkhe Main — V2 → V3 upgrade');

        $dryRun = (bool) $this->option('dry-run');
        $report = [];

        $report[] = $this->reshapeRolesAndPermissions($files, $dryRun);
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
        $this->line('    (Arkhe\\Main\\Livewire\\ListUsers, ListRoles, ListPermissions).');

        return self::SUCCESS;
    }

    /**
     * Rewrite the V2 `roles` / `permissions` entries of config/arkhe.php into
     * their V3 layout: `roles` becomes a key => name map, `permissions` a
     * flat list, and the V2 role => permissions mapping moves verbatim to a
     * new `role_permissions` entry (enum keys and comments survive). Returns
     * a human-readable status line.
     */
    private function reshapeRolesAndPermissions(Filesystem $files, bool $dryRun): string
    {
        $path = config_path('arkhe.php');
        if (! $files->exists($path)) {
            return 'reshape: skipped (no config/arkhe.php — run arkhe:main:install first)';
        }

        $values = require $path;
        if (! is_array($values)) {
            return 'reshape: skipped (config/arkhe.php does not return an array)';
        }

        $roles = is_array($values['roles'] ?? null) ? $values['roles'] : [];
        $permissions = is_array($values['permissions'] ?? null) ? $values['permissions'] : [];

        $rolesAreV2 = array_filter($roles, 'is_array') !== [];
        $permissionsAreV2 = array_filter($permissions, 'is_array') !== [];

        if (! $rolesAreV2 && ! $permissionsAreV2) {
            return 'reshape: roles/permissions already use the V3 layout';
        }

        $contents = (string) $files->get($path);

        if ($rolesAreV2 && preg_match("/'role_permissions'\s*=>/m", $contents)) {
            return 'reshape: WARNING — role_permissions already exists but roles still use the V2 layout, merge them manually';
        }

        /** @var array<int, array{key: string, span: array{start: int, end: int, indent: string}, replacement: string}> $replacements */
        $replacements = [];

        if ($permissionsAreV2) {
            $span = $this->topLevelArraySpan($contents, 'permissions');
            if ($span === null) {
                return 'reshape: WARNING — could not locate the top-level permissions array, reshape it manually';
            }

            $flat = [];
            foreach ($permissions as $group) {
                foreach ((array) $group as $permission) {
                    if (! is_string($permission)) {
                        return 'reshape: WARNING — permissions contain non-string entries, reshape them manually';
                    }
                    $flat[] = $permission;
                }
            }

            $replacements[] = [
                'key' => 'permissions',
                'span' => $span,
                'replacement' => $this->renderStringList(array_values(array_unique($flat)), $span['indent']),
            ];
        }

        if ($rolesAreV2) {
            $span = $this->topLevelArraySpan($contents, 'roles');
            if ($span === null) {
                return 'reshape: WARNING — could not locate the top-level roles array, reshape it manually';
            }

            $names = array_map('strval', array_keys($roles));
            $replacement = $this->renderIdentityMap($names, $span['indent']);

            // The V2 body (role => permissions) IS the V3 mapping: move it
            // verbatim so enum keys and inline comments survive.
            $body = substr($contents, $span['start'], $span['end'] - $span['start']);
            $replacement .= ",\n\n".$span['indent']."'role_permissions' => ".$body;

            $replacements[] = ['key' => 'roles', 'span' => $span, 'replacement' => $replacement];
        }

        if (! confirm(label: 'Rewrite roles/permissions into the V3 layout?', default: true)) {
            return 'reshape: skipped by user';
        }

        $this->reshapeAddsRolePermissions = $rolesAreV2;

        $summary = implode(' + ', array_filter([
            $rolesAreV2 ? 'roles → key map (mapping moved to role_permissions)' : null,
            $permissionsAreV2 ? 'permissions → flat list' : null,
        ]));

        if ($dryRun) {
            $this->newLine();
            foreach ($replacements as $item) {
                $this->line($item['span']['indent']."'".$item['key']."' => ".$item['replacement'].',');
            }

            return 'reshape: would rewrite '.$summary.' (dry run)';
        }

        usort($replacements, fn (array $a, array $b): int => $b['span']['start'] <=> $a['span']['start']);

        foreach ($replacements as $item) {
            $contents = substr($contents, 0, $item['span']['start'])
                .$item['replacement']
                .substr($contents, $item['span']['end']);
        }

        $files->put($path, $contents);

        return 'reshape: rewrote '.$summary;
    }

    /**
     * Locate the `[ … ]` value of a top-level config entry (`'key' => […]`)
     * with the PHP tokenizer, so strings, comments and nested arrays never
     * confuse the match. `start` is the byte offset of the opening bracket,
     * `end` sits just past the closing one, `indent` is the entry line's
     * leading whitespace. Returns null when the key is missing at the root
     * level of the returned array (or its value is not an array literal).
     *
     * @return array{start: int, end: int, indent: string}|null
     */
    private function topLevelArraySpan(string $contents, string $key): ?array
    {
        $tokens = token_get_all($contents);

        $offsets = [];
        $pos = 0;
        foreach ($tokens as $i => $token) {
            $offsets[$i] = $pos;
            $pos += strlen(is_array($token) ? $token[1] : $token);
        }

        $depth = 0;
        foreach ($tokens as $i => $token) {
            if ($token === '[' || (is_array($token) && $token[0] === T_ATTRIBUTE)) {
                $depth++;

                continue;
            }
            if ($token === ']') {
                $depth--;

                continue;
            }
            if (
                $depth !== 1
                || ! is_array($token)
                || $token[0] !== T_CONSTANT_ENCAPSED_STRING
                || ! in_array($token[1], ["'{$key}'", '"'.$key.'"'], true)
            ) {
                continue;
            }

            $arrow = $this->nextSignificantToken($tokens, $i);
            if ($arrow === null || ! is_array($tokens[$arrow]) || $tokens[$arrow][0] !== T_DOUBLE_ARROW) {
                continue;
            }

            $open = $this->nextSignificantToken($tokens, $arrow);
            if ($open === null || $tokens[$open] !== '[') {
                return null;
            }

            $end = $this->matchingBracketEnd($tokens, $offsets, $open);
            if ($end === null) {
                return null;
            }

            $lineStart = strrpos(substr($contents, 0, $offsets[$i]), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $indent = substr($contents, $lineStart, $offsets[$i] - $lineStart);

            return [
                'start' => $offsets[$open],
                'end' => $end,
                'indent' => trim($indent, " \t") === '' ? $indent : '',
            ];
        }

        return null;
    }

    /** @param array<int, array|string> $tokens */
    private function nextSignificantToken(array $tokens, int $from): ?int
    {
        for ($i = $from + 1, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * Byte offset just past the `]` matching the `[` at $openIndex.
     *
     * @param  array<int, array|string>  $tokens
     * @param  array<int, int>  $offsets
     */
    private function matchingBracketEnd(array $tokens, array $offsets, int $openIndex): ?int
    {
        $depth = 0;
        for ($i = $openIndex, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i] === '[' || (is_array($tokens[$i]) && $tokens[$i][0] === T_ATTRIBUTE)) {
                $depth++;
            } elseif ($tokens[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    return $offsets[$i] + 1;
                }
            }
        }

        return null;
    }

    /** @param array<int, string> $names */
    private function renderIdentityMap(array $names, string $indent): string
    {
        $lines = ['['];
        foreach ($names as $name) {
            $literal = var_export($name, true);
            $lines[] = $indent.'    '.$literal.' => '.$literal.',';
        }
        $lines[] = $indent.']';

        return implode("\n", $lines);
    }

    /** @param array<int, string> $items */
    private function renderStringList(array $items, string $indent): string
    {
        $lines = ['['];
        foreach ($items as $item) {
            $lines[] = $indent.'    '.var_export($item, true).',';
        }
        $lines[] = $indent.']';

        return implode("\n", $lines);
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
        if ($this->reshapeAddsRolePermissions) {
            unset($missing['role_permissions']);
        }
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
            'override_fortify_redirect' => "'override_fortify_redirect' => env('ARKHE_OVERRIDE_FORTIFY_REDIRECT', true),",
            'middleware' => "'middleware' => ['web', 'auth', 'arkhe.backend'],",
            'avatar_disk' => "'avatar_disk' => env('ARKHE_AVATAR_DISK', 'public'),",
            'avatar_path' => "'avatar_path' => env('ARKHE_AVATAR_PATH', 'avatars'),",
            'per_page' => "'per_page' => 15,",
            'user_model' => "'user_model' => null,",
            'role_permissions' => "'role_permissions' => [\n        'root' => ['*'],\n    ],",
            'backend_permission' => "'backend_permission' => 'access-backend',",
            'root_permission' => "'root_permission' => 'manage-roles',",
            // Appended so the key is visible in the published config, not to
            // turn anything on: `false` is the default, and absent reads the
            // same as false. Note `middleware` above keeps the V3 stack — the
            // strong-auth middleware is wired in routes/arkhe.php instead, so
            // that apps with a frozen published config still receive it.
            'strong_auth' => "'strong_auth' => [\n        'enforce' => env('ARKHE_STRONG_AUTH', false),\n        'route'   => null,\n    ],",
            'components' => "'components' => [\n        'list-users'       => \\Arkhe\\Main\\Livewire\\ListUsers::class,\n        'list-roles'       => \\Arkhe\\Main\\Livewire\\ListRoles::class,\n        'list-permissions' => \\Arkhe\\Main\\Livewire\\ListPermissions::class,\n    ],",
            'features' => "'features' => [\n        'cookie_consent' => false,\n        'seo' => false,\n    ],",
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
