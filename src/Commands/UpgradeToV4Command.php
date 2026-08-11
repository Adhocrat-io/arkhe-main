<?php

declare(strict_types=1);

namespace Arkhe\Main\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;

/**
 * Migrates a host application from Arkhe Main V3 to V4.
 *
 * V4 carries one breaking change — the dashboard left the package — plus a
 * backend reshape that moved creation and editing onto their own pages. Neither
 * touches PHP that host apps wrote against `Arkhe\Main\…`, so there is no
 * search-replace to run. What this command handles instead:
 *
 * 1. **Dead config keys.** `dashboard_route`, `dashboard_route_name` and
 *    `override_fortify_redirect` no longer do anything. They are inert rather
 *    than harmful, so removing them is tidying — but tidying nobody wants to do
 *    by hand across a fleet of apps, comment banner included.
 *
 * 2. **Published views that reference routes the package dropped.** This is the
 *    one that actually breaks a page: a published `list-roles.blade.php` still
 *    calls `route('arkhe.roles.create')`, which now throws
 *    `RouteNotFoundException` on *render*, not on click. Reported, never
 *    rewritten — a published view is the consumer's file, and Arkhe guessing at
 *    edits inside it would be worse than saying what to look at.
 *
 * 3. **Subclasses whose overridden hooks are no longer called.** An app that
 *    subclassed `ListUsers` to hook `afterCreate` still runs, silently doing
 *    nothing, because saving moved to `EditUser`. Silence is the problem; the
 *    command names the files.
 *
 * Detection over rewriting is the rule here. The V2 → V3 command could rewrite
 * safely because it targeted config it had itself shipped; here the risky
 * surface is code the consumer owns.
 *
 * @see UpgradeFromV2Command for the V2 → V3 path, which a `^1`/`^2` app must run first.
 */
class UpgradeToV4Command extends Command
{
    protected $signature = 'arkhe:main:upgrade-to-v4 {--dry-run : Report changes without writing anything}';

    protected $description = 'Migrate a host application from Arkhe Main V3 to V4 (drop dead config keys, report stale views and overrides).';

    /**
     * Config keys the dashboard removal left behind, with the env var each one
     * reads so the report can name what to clean out of `.env` too.
     *
     * @var array<string, string>
     */
    private const DEAD_KEYS = [
        'dashboard_route' => 'ARKHE_DASHBOARD_ROUTE',
        'dashboard_route_name' => 'ARKHE_DASHBOARD_ROUTE_NAME',
        'override_fortify_redirect' => 'ARKHE_OVERRIDE_FORTIFY_REDIRECT',
    ];

    /**
     * Route names the package no longer registers, mapped to what to do about
     * them. A published view calling one of these throws when the page renders.
     *
     * @var array<string, string>
     */
    private const DROPPED_ROUTES = [
        'arkhe.roles.create' => 'role creation left the interface — remove the button, or take the package view back',
        'arkhe.dashboard' => 'the dashboard left the package — point at your own route',
    ];

    /**
     * Hooks that still exist on the list components but are never called by the
     * package any more. An override of one of these is dead code that fails
     * silently, which is exactly why it needs reporting.
     *
     * @var array<int, string>
     */
    private const MOVED_HOOKS = ['beforeSave', 'afterCreate', 'afterUpdate', 'beforeDelete'];

    public function handle(Filesystem $files): int
    {
        $this->components->info('Arkhe Main — V3 → V4 upgrade');

        $dryRun = (bool) $this->option('dry-run');

        if ($this->looksLikeV2Config($files)) {
            $this->components->error('This config still has the V2 layout.');
            $this->line('  Run <fg=yellow>php artisan arkhe:main:upgrade-from-v2</> first, then come back.');

            return self::FAILURE;
        }

        $report = array_filter([
            $this->dropDeadKeys($files, $dryRun),
            $this->reportStaleViews($files),
            $this->reportMovedHooks($files),
            $this->reportDroppedEnvVars($files),
        ]);

        $this->newLine();
        $this->components->info('Summary');
        foreach ($report as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->components->warn('Manual steps remaining:');
        $this->line('  • Run: php artisan arkhe:main:install   (idempotent — answer "no" to what is already done)');
        $this->line('  • If you had set ARKHE_DASHBOARD_ROUTE_NAME=dashboard, declare your own');
        $this->line('    route(\'dashboard\') — see UPGRADE.md. Check with:');
        $this->line('      php artisan route:list --name=dashboard');

        return self::SUCCESS;
    }

    /**
     * A V2-shaped config would make every check below meaningless — the keys are
     * not where this command looks. Detected by the shape V3 introduced:
     * `roles` as a key => name map rather than a flat list.
     */
    private function looksLikeV2Config(Filesystem $files): bool
    {
        $path = config_path('arkhe.php');

        if (! $files->exists($path)) {
            return false;
        }

        $contents = $files->get($path);

        return ! str_contains($contents, "'role_permissions'")
            && ! str_contains($contents, "'backend_permission'");
    }

    /**
     * Strip the three dead keys from the published config, comment banner and
     * all. Returns a status line.
     */
    private function dropDeadKeys(Filesystem $files, bool $dryRun): string
    {
        $path = config_path('arkhe.php');

        if (! $files->exists($path)) {
            return 'config/arkhe.php — not published, nothing to clean';
        }

        $contents = $files->get($path);
        $present = array_values(array_filter(
            array_keys(self::DEAD_KEYS),
            fn (string $key): bool => $this->entrySpan($contents, $key) !== null,
        ));

        if ($present === []) {
            return 'config/arkhe.php — no dead keys left';
        }

        $this->newLine();
        $this->components->warn('Dead config keys found (the dashboard left the package in 4.0):');
        foreach ($present as $key) {
            $this->line("  • '{$key}'");
        }

        if ($dryRun) {
            return 'config/arkhe.php — '.count($present).' dead key(s) would be removed (dry run)';
        }

        if (! confirm('Remove them from config/arkhe.php?', default: true)) {
            return 'config/arkhe.php — skipped by choice, '.count($present).' dead key(s) left in place';
        }

        // Remove from the bottom up so earlier offsets stay valid.
        foreach (array_reverse($present) as $key) {
            $span = $this->entrySpan($contents, $key);
            if ($span === null) {
                continue;
            }
            $contents = substr($contents, 0, $span['start']).substr($contents, $span['end']);
        }

        $files->put($path, $contents);

        return 'config/arkhe.php — removed '.count($present).' dead key(s)';
    }

    /**
     * Byte span of a top-level scalar entry, banner comment included.
     *
     * Token-based rather than a regex: the key name also appears inside comment
     * prose ("When Fortify is installed and `dashboard_route` is set…"), and a
     * textual match would happily cut a file in half. `token_get_all()` tells
     * a real array key from a mention of it.
     *
     * The span runs from the start of whatever precedes the entry — its
     * `/* … *\/` banner and the blank line before it — through the comma that
     * ends it, so removal leaves no orphaned documentation behind.
     *
     * @return array{start: int, end: int}|null
     */
    private function entrySpan(string $contents, string $key): ?array
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
            if ($token === '[') {
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

            $arrow = $this->nextSignificant($tokens, $i);
            if ($arrow === null || ! is_array($tokens[$arrow]) || $tokens[$arrow][0] !== T_DOUBLE_ARROW) {
                continue;
            }

            $end = $this->entryEnd($tokens, $offsets, $arrow);
            if ($end === null) {
                return null;
            }

            return ['start' => $this->entryStart($tokens, $offsets, $i), 'end' => $end];
        }

        return null;
    }

    /**
     * Walk backwards from the key over its banner comment and the whitespace
     * around it, stopping at the first real token.
     *
     * Token-based on purpose. Searching the raw text for the previous comma
     * finds whichever comma comes last — including one *inside* the preceding
     * entry's array literal — and the cut then starts mid-banner, leaving an
     * unterminated `/*` that no longer parses. The token stream has no such
     * ambiguity: a comment is one token, a comma inside a nested array is not
     * at this depth.
     *
     * @param  array<int, array|string>  $tokens
     * @param  array<int, int>  $offsets
     */
    private function entryStart(array $tokens, array $offsets, int $keyIndex): int
    {
        $start = $offsets[$keyIndex];

        for ($i = $keyIndex - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                break;
            }

            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $start = $offsets[$i];

                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                // Keep one newline so the previous entry does not gain a
                // trailing blank line, but absorb the rest.
                $start = $offsets[$i] + (str_contains($token[1], "\n") ? 1 : 0);

                continue;
            }

            break;
        }

        return $start;
    }

    /**
     * Byte offset just past the entry's trailing comma, so the value itself may
     * span several lines (`env('X', 'default')`, a closure, a nested array)
     * without the caller having to care.
     */
    private function entryEnd(array $tokens, array $offsets, int $arrow): ?int
    {
        $depth = 0;

        for ($i = $arrow + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '[' || $token === '(') {
                $depth++;

                continue;
            }
            if ($token === ']' || $token === ')') {
                if ($depth === 0) {
                    // Closing the parent array without a trailing comma.
                    return $offsets[$i];
                }
                $depth--;

                continue;
            }
            if ($token === ',' && $depth === 0) {
                return $offsets[$i] + 1;
            }
        }

        return null;
    }

    /** @param array<int, array|string> $tokens */
    private function nextSignificant(array $tokens, int $from): ?int
    {
        for ($i = $from + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * Published Arkhe views calling a route the package no longer registers.
     *
     * Reported, not rewritten: these files belong to the consumer, and the fix
     * depends on what they wanted the button to do. The failure mode is worth
     * the noise — `route('arkhe.roles.create')` throws when the page renders,
     * so the list becomes unreachable rather than merely showing a dead button.
     */
    private function reportStaleViews(Filesystem $files): string
    {
        $dir = resource_path('views/vendor/arkhe');

        if (! $files->isDirectory($dir)) {
            return 'published views — none, nothing to check';
        }

        $hits = [];
        foreach ($files->allFiles($dir) as $file) {
            $contents = $files->get($file->getPathname());
            foreach (self::DROPPED_ROUTES as $route => $advice) {
                if (str_contains($contents, "'{$route}'") || str_contains($contents, '"'.$route.'"')) {
                    $hits[] = [
                        'file' => str_replace(base_path().'/', '', $file->getPathname()),
                        'route' => $route,
                        'advice' => $advice,
                    ];
                }
            }
        }

        if ($hits === []) {
            return 'published views — no reference to dropped routes';
        }

        $this->newLine();
        $this->components->error('Published views reference routes that no longer exist:');
        foreach ($hits as $hit) {
            $this->line("  • {$hit['file']}");
            $this->line("      route('{$hit['route']}') — {$hit['advice']}");
        }
        $this->line('  These throw RouteNotFoundException when the page renders, not when clicked.');

        return 'published views — '.count($hits).' stale route reference(s), see above';
    }

    /**
     * Subclasses overriding a hook the list components no longer call.
     *
     * The override still compiles and still runs — it just never fires, because
     * saving moved to `EditUser` / `EditRole`. Failing silently is precisely
     * what makes this worth a report.
     */
    private function reportMovedHooks(Filesystem $files): string
    {
        $dir = app_path();

        if (! $files->isDirectory($dir)) {
            return 'subclasses — app/ not readable, skipped';
        }

        $hits = [];
        foreach ($files->allFiles($dir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $files->get($file->getPathname());

            if (! preg_match('/extends\s+\\\\?(?:Arkhe\\\\Main\\\\Livewire\\\\)?(ListUsers|ListRoles)\b/', $contents, $m)) {
                continue;
            }

            $found = array_values(array_filter(
                self::MOVED_HOOKS,
                fn (string $hook): bool => (bool) preg_match('/function\s+'.$hook.'\s*\(/', $contents),
            ));

            if ($found !== []) {
                $hits[] = [
                    'file' => str_replace(base_path().'/', '', $file->getPathname()),
                    'parent' => $m[1],
                    'hooks' => $found,
                ];
            }
        }

        if ($hits === []) {
            return 'subclasses — no orphaned hook override found';
        }

        $target = ['ListUsers' => 'EditUser', 'ListRoles' => 'EditRole'];

        $this->newLine();
        $this->components->warn('Hook overrides that are no longer called:');
        foreach ($hits as $hit) {
            $this->line("  • {$hit['file']} (extends {$hit['parent']})");
            $this->line('      '.implode(', ', $hit['hooks']).' — move onto '.$target[$hit['parent']].', same signatures');
        }
        $this->line('  These still compile and still run — they just never fire.');

        return 'subclasses — '.count($hits).' file(s) with orphaned hooks, see above';
    }

    /**
     * Dashboard env vars left in `.env`. Harmless, but they read as live
     * configuration to whoever opens the file next.
     */
    private function reportDroppedEnvVars(Filesystem $files): string
    {
        $path = base_path('.env');

        if (! $files->exists($path)) {
            return '';
        }

        $contents = $files->get($path);
        $present = array_values(array_filter(
            self::DEAD_KEYS,
            fn (string $env): bool => (bool) preg_match('/^\s*'.preg_quote($env, '/').'\s*=/m', $contents),
        ));

        if ($present === []) {
            return '.env — no dashboard variable left';
        }

        $this->newLine();
        $this->components->warn('Dashboard variables still in .env (no longer read):');
        foreach ($present as $env) {
            $this->line("  • {$env}");
        }
        $this->line('  Remove them by hand — Arkhe does not edit .env.');

        return '.env — '.count($present).' dead variable(s), see above';
    }
}
