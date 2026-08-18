<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->configPath = config_path('arkhe.php');
    $this->originalConfig = $this->files->exists($this->configPath)
        ? $this->files->get($this->configPath)
        : null;

    $this->viewsDir = resource_path('views/vendor/arkhe/livewire');
    $this->appDir = app_path('Livewire');
});

afterEach(function (): void {
    if ($this->originalConfig === null) {
        $this->files->delete($this->configPath);
    } else {
        $this->files->put($this->configPath, $this->originalConfig);
    }

    $this->files->deleteDirectory(resource_path('views/vendor/arkhe'));
    $this->files->deleteDirectory($this->appDir);
});

// ─── Dead config keys ────────────────────────────────────────────────────

it('removes the three dashboard keys when confirmed', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsConfirmation('Remove them from config/arkhe.php?', 'yes')
        ->assertSuccessful();

    $patched = $this->files->get($this->configPath);

    expect($patched)->not->toContain("'dashboard_route'")
        ->not->toContain("'dashboard_route_name'")
        ->not->toContain("'override_fortify_redirect'");
});

// The whole point of editing through `token_get_all()` rather than a regex: the
// file has to still parse, and every neighbouring key has to survive. A regex
// would happily cut from a comment mentioning the key to the wrong comma.
it('leaves the config valid and its other keys intact', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsConfirmation('Remove them from config/arkhe.php?', 'yes')
        ->assertSuccessful();

    // Loaded the way Laravel loads it, which is the condition that matters:
    // a file that no longer parses takes the whole app down at boot.
    $parsed = require $this->configPath;
    expect($parsed)->toBeArray();

    // Neighbours of the removed entries are still there, values included.
    expect($parsed)->toHaveKeys(['admin', 'middleware', 'per_page', 'roles', 'role_permissions'])
        ->and($parsed['per_page'])->toBe(15)
        ->and($parsed['middleware'])->toBe(['web', 'auth', 'arkhe.backend'])
        ->and($parsed)->not->toHaveKey('dashboard_route');
});

// The banner comment above each key documents a feature that no longer exists.
// Leaving it behind would be worse than leaving the key: an orphaned block
// explaining a dashboard the package does not ship.
it('takes the comment banner with the key', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsConfirmation('Remove them from config/arkhe.php?', 'yes')
        ->assertSuccessful();

    expect($this->files->get($this->configPath))
        ->not->toContain('Dashboard route (opt-in)')
        ->not->toContain("Override Fortify's `home` redirect");
});

it('writes nothing on a dry run', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $before = $this->files->get($this->configPath);

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('would be removed')
        ->assertSuccessful();

    expect($this->files->get($this->configPath))->toBe($before);
});

it('leaves the file alone when the removal is declined', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $before = $this->files->get($this->configPath);

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsConfirmation('Remove them from config/arkhe.php?', 'no')
        ->assertSuccessful();

    expect($this->files->get($this->configPath))->toBe($before);
});

// Re-running an upgrade command is normal — after a botched deploy, on a second
// app, out of doubt. The second run must be a no-op, not an error.
it('is idempotent', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsConfirmation('Remove them from config/arkhe.php?', 'yes')
        ->assertSuccessful();

    $afterFirst = $this->files->get($this->configPath);

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsOutputToContain('no dead keys left')
        ->assertSuccessful();

    expect($this->files->get($this->configPath))->toBe($afterFirst);
});

it('refuses to run against a V2-shaped config', function (): void {
    $this->files->put($this->configPath, "<?php\n\nreturn [\n    'permissions' => [],\n    'roles' => [],\n];\n");

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsOutputToContain('upgrade-from-v2')
        ->assertFailed();
});

// ─── Published views ─────────────────────────────────────────────────────

// The one that actually breaks a page: `route('arkhe.roles.create')` throws on
// render, so a published list-roles view takes the whole page down rather than
// showing a dead button.
it('reports a published view calling a dropped route', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->viewsDir);
    $this->files->put(
        $this->viewsDir.'/list-roles.blade.php',
        '<flux:button :href="route(\'arkhe.roles.create\')">Créer</flux:button>',
    );

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('list-roles.blade.php')
        ->expectsOutputToContain('RouteNotFoundException')
        ->assertSuccessful();
});

it('says nothing about published views that are clean', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->viewsDir);
    $this->files->put($this->viewsDir.'/list-roles.blade.php', '<div>rien de périmé ici</div>');

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('no reference to dropped routes')
        ->assertSuccessful();
});

// Reported, never rewritten: a published view belongs to the consumer, and the
// right fix depends on what they wanted the button to do.
it('does not rewrite the published view it reports', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->viewsDir);
    $view = $this->viewsDir.'/list-roles.blade.php';
    $original = '<flux:button :href="route(\'arkhe.roles.create\')">Créer</flux:button>';
    $this->files->put($view, $original);

    $this->artisan('arkhe:main:upgrade-to-v4')
        ->expectsConfirmation('Remove them from config/arkhe.php?', 'yes')
        ->assertSuccessful();

    expect($this->files->get($view))->toBe($original);
});

// Found on a real consumer: a sidebar partial published before roles and
// permissions were merged still lists "Permissions" next to "Roles &
// permissions". Nothing throws — the link redirects — so the duplicate just
// sits there looking legitimate. Silence is what makes it worth reporting.
it('reports a published view still linking to the permissions page', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists(resource_path('views/vendor/arkhe/partials'));
    $this->files->put(
        resource_path('views/vendor/arkhe/partials/sidebar-items.blade.php'),
        '<flux:sidebar.item :href="route(\'arkhe.permissions.index\')">Permissions</flux:sidebar.item>',
    );

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('sidebar-items.blade.php')
        ->expectsOutputToContain('duplicate')
        ->assertSuccessful();
});

// ─── Orphaned hook overrides ─────────────────────────────────────────────

// These still compile and still run — they just never fire, because saving
// moved to EditUser/EditRole. Silence is what makes them worth reporting.
it('reports a subclass whose hook is no longer called', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->appDir);
    $this->files->put($this->appDir.'/AppListUsers.php', <<<'PHP'
        <?php

        namespace App\Livewire;

        class AppListUsers extends \Arkhe\Main\Livewire\ListUsers
        {
            protected function afterCreate($user, array $payload): void
            {
                // never fires any more
            }
        }
        PHP);

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('AppListUsers.php')
        ->expectsOutputToContain('EditUser')
        ->assertSuccessful();
});

it('ignores a subclass that overrides nothing moved', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->appDir);
    $this->files->put($this->appDir.'/AppListUsers.php', <<<'PHP'
        <?php

        namespace App\Livewire;

        class AppListUsers extends \Arkhe\Main\Livewire\ListUsers
        {
            public function resetPassword(int $id): void
            {
                // still a perfectly good extra wire:click target
            }
        }
        PHP);

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('no orphaned hook override')
        ->assertSuccessful();
});

// ─── Form objects still on toArray() ─────────────────────────────────────

// Shipped in 3.3.0, but an app jumping 3.1 → 4.0 never reads those notes. An
// override left in place keeps the serialisation bug alive rather than merely
// being out of date.
it('reports a Form subclass still overriding toArray', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->appDir);
    $this->files->put($this->appDir.'/MonUserForm.php', <<<'PHP'
        <?php

        namespace App\Livewire;

        class MonUserForm extends \Arkhe\Main\Livewire\Forms\UserForm
        {
            public function toArray(): array
            {
                return array_merge(parent::toArray(), ['service_id' => 1]);
            }
        }
        PHP);

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('MonUserForm.php')
        ->expectsOutputToContain('toPayload()')
        ->assertSuccessful();
});

it('reports a component still calling toArray on a form', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->appDir);
    $this->files->put($this->appDir.'/AppEditUser.php', <<<'PHP'
        <?php

        namespace App\Livewire;

        class AppEditUser extends \Arkhe\Main\Livewire\EditUser
        {
            public function save(): void
            {
                $payload = $this->userForm->toArray();
            }
        }
        PHP);

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('AppEditUser.php')
        ->assertSuccessful();
});

it('says nothing when the forms are already renamed', function (): void {
    $this->files->put($this->configPath, fixtureV4Config());
    $this->files->ensureDirectoryExists($this->appDir);
    $this->files->put($this->appDir.'/MonUserForm.php', <<<'PHP'
        <?php

        namespace App\Livewire;

        class MonUserForm extends \Arkhe\Main\Livewire\Forms\UserForm
        {
            public function toPayload(): array
            {
                return array_merge(parent::toPayload(), ['service_id' => 1]);
            }
        }
        PHP);

    $this->artisan('arkhe:main:upgrade-to-v4', ['--dry-run' => true])
        ->expectsOutputToContain('no stale toArray()')
        ->assertSuccessful();
});

/**
 * A published V3 config, shaped like the real one: each dashboard key sits
 * under the banner comment the package shipped, with live neighbours on either
 * side so a sloppy removal would show up as collateral damage.
 */
function fixtureV4Config(): string
{
    return <<<'PHP'
        <?php

        declare(strict_types=1);

        return [
            'admin' => [
                'prefix' => env('ARKHE_ADMIN_PREFIX', 'administration'),
                'layout' => env('ARKHE_ADMIN_LAYOUT', 'layouts::app'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Dashboard route (opt-in)
            |--------------------------------------------------------------------------
            |
            | When set, Arkhe registers a top-level dashboard at this path with the
            | named route `arkhe.dashboard`. Leave null to keep your app's existing
            | dashboard untouched.
            |
            */
            'dashboard_route' => env('ARKHE_DASHBOARD_ROUTE'),

            /*
            |--------------------------------------------------------------------------
            | Dashboard route name
            |--------------------------------------------------------------------------
            |
            | Named route under which the dashboard is registered.
            |
            */
            'dashboard_route_name' => env('ARKHE_DASHBOARD_ROUTE_NAME', 'arkhe.dashboard'),

            'middleware' => ['web', 'auth', 'arkhe.backend'],

            /*
            |--------------------------------------------------------------------------
            | Override Fortify's `home` redirect
            |--------------------------------------------------------------------------
            |
            | When Fortify is installed and `dashboard_route` is set, Arkhe rewrites
            | `config('fortify.home')` at boot.
            |
            */
            'override_fortify_redirect' => env('ARKHE_OVERRIDE_FORTIFY_REDIRECT', true),

            'per_page' => 15,
            'roles' => ['root' => 'root'],
            'role_permissions' => ['root' => ['*']],
            'backend_permission' => 'access-backend',
        ];
        PHP;
}
