<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->configPath = config_path('arkhe.php');
    $this->originalConfig = $this->files->exists($this->configPath)
        ? $this->files->get($this->configPath)
        : null;
});

afterEach(function (): void {
    if ($this->originalConfig === null) {
        $this->files->delete($this->configPath);
    } else {
        $this->files->put($this->configPath, $this->originalConfig);
    }
});

it('reports nothing to add when the host config already has V3 keys', function (): void {
    $this->files->put($this->configPath, fixtureV3Config());

    $this->artisan('arkhe:main:upgrade-from-v2', ['--dry-run' => true])
        ->expectsOutputToContain('already use the V3 layout')
        ->expectsOutputToContain('already aligned with V3')
        ->assertSuccessful();
});

it('appends missing V3 keys to a V2-shaped config when confirmed', function (): void {
    $this->files->put($this->configPath, fixtureV2Config());

    $this->artisan('arkhe:main:upgrade-from-v2')
        ->expectsConfirmation('Rewrite roles/permissions into the V3 layout?', 'yes')
        ->expectsConfirmation(
            'Append 10 missing V3 keys to config/arkhe.php?',
            'yes',
        )
        ->assertSuccessful();

    $patched = $this->files->get($this->configPath);

    expect($patched)->toContain("'role_permissions'")
        ->toContain("'backend_permission' => 'access-backend'")
        ->toContain("'components'")
        ->toContain('// ── V3 additions');

    // V2 entries must remain untouched.
    expect($patched)->toContain("'admin' =>")
        ->toContain("'role_hierarchy' =>")
        ->toContain("'role_labels' =>");
});

it('reshapes V2 roles and permissions into the V3 layout', function (): void {
    $this->files->put($this->configPath, fixtureV2Config());

    $this->artisan('arkhe:main:upgrade-from-v2')
        ->expectsConfirmation('Rewrite roles/permissions into the V3 layout?', 'yes')
        ->expectsConfirmation(
            'Append 10 missing V3 keys to config/arkhe.php?',
            'yes',
        )
        ->assertSuccessful();

    $values = require $this->configPath;

    expect($values['roles'])->toBe(['root' => 'root'])
        ->and($values['permissions'])->toBe(['view-user', 'create-user', 'update-user', 'delete-user'])
        ->and($values['role_permissions'])->toBe(['root' => ['*']])
        ->and($values['admin']['roles'])->toBe(['root', 'administrator']);
});

it('moves the V2 roles body verbatim into role_permissions and dedupes permissions', function (): void {
    $this->files->put($this->configPath, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'admin' => [
        'roles' => ['root'],
    ],
    'permissions' => [
        'manage-users' => ['view-user'],
        'manage-posts' => ['view-post', 'view-user'],
    ],
    'roles' => [
        // wildcard: every permission
        'root' => ['*'],
        'editor' => ['view-post'],
    ],
];
PHP);

    $this->artisan('arkhe:main:upgrade-from-v2')
        ->expectsConfirmation('Rewrite roles/permissions into the V3 layout?', 'yes')
        ->expectsConfirmation(
            'Append 10 missing V3 keys to config/arkhe.php?',
            'yes',
        )
        ->assertSuccessful();

    // Comments inside the V2 mapping must survive the move to role_permissions.
    expect($this->files->get($this->configPath))->toContain('// wildcard: every permission');

    $values = require $this->configPath;

    expect($values['roles'])->toBe(['root' => 'root', 'editor' => 'editor'])
        ->and($values['role_permissions'])->toBe(['root' => ['*'], 'editor' => ['view-post']])
        ->and($values['permissions'])->toBe(['view-user', 'view-post']);
});

it('warns instead of reshaping when role_permissions already exists next to V2 roles', function (): void {
    $original = <<<'PHP'
<?php

return [
    'roles' => [
        'root' => ['*'],
    ],
    'role_permissions' => [
        'root' => ['*'],
    ],
];
PHP;
    $this->files->put($this->configPath, $original);

    $this->artisan('arkhe:main:upgrade-from-v2', ['--dry-run' => true])
        ->expectsConfirmation(
            'Append 10 missing V3 keys to config/arkhe.php?',
            'no',
        )
        ->expectsOutputToContain('merge them manually')
        ->assertSuccessful();

    expect($this->files->get($this->configPath))->toBe($original);
});

it('does not modify the file in dry-run mode', function (): void {
    $original = fixtureV2Config();
    $this->files->put($this->configPath, $original);

    // Even in dry-run, a confirmed reshape removes role_permissions from the
    // keys to append (11 → 10): the reshape itself will create it.
    $this->artisan('arkhe:main:upgrade-from-v2', ['--dry-run' => true])
        ->expectsConfirmation('Rewrite roles/permissions into the V3 layout?', 'yes')
        ->expectsConfirmation(
            'Append 10 missing V3 keys to config/arkhe.php?',
            'yes',
        )
        ->expectsOutputToContain('dry run')
        ->assertSuccessful();

    expect($this->files->get($this->configPath))->toBe($original);
});

it('rewrites V2 livewire aliases inside blade files', function (): void {
    $viewsRoot = resource_path('views');
    $this->files->ensureDirectoryExists($viewsRoot.'/components/layouts');
    $sidebar = $viewsRoot.'/components/layouts/sidebar.blade.php';
    $this->files->put(
        $sidebar,
        '<livewire:arkhe.main.livewire.admin.users.users-list /> '.
        '<livewire:arkhe.main.livewire.admin.users.roles.roles-list />',
    );

    $this->files->put($this->configPath, fixtureV3Config());

    $this->artisan('arkhe:main:upgrade-from-v2')->assertSuccessful();

    $patched = $this->files->get($sidebar);
    expect($patched)->toContain('arkhe.list-users')
        ->toContain('arkhe.list-roles')
        ->not->toContain('arkhe.main.livewire');

    $this->files->delete($sidebar);
});

function fixtureV2Config(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

return [
    'admin' => [
        'prefix' => env('ARKHE_ADMIN_PREFIX', 'administration'),
        'layout' => env('ARKHE_ADMIN_LAYOUT', 'layouts::app'),
        'roles'  => ['root', 'administrator'],
    ],
    'permissions' => [
        'manage-users' => ['view-user', 'create-user', 'update-user', 'delete-user'],
    ],
    'roles' => [
        'root' => ['*'],
    ],
    'role_hierarchy' => [],
    'role_labels' => [],
];
PHP;
}

function fixtureV3Config(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

return [
    'admin' => [
        'prefix' => env('ARKHE_ADMIN_PREFIX', 'administration'),
        'layout' => env('ARKHE_ADMIN_LAYOUT', 'layouts::app'),
        'roles'  => ['root', 'administrator'],
    ],
    'dashboard_route' => env('ARKHE_DASHBOARD_ROUTE'),
    'dashboard_route_name' => env('ARKHE_DASHBOARD_ROUTE_NAME', 'arkhe.dashboard'),
    'override_fortify_redirect' => env('ARKHE_OVERRIDE_FORTIFY_REDIRECT', true),
    'middleware' => ['web', 'auth', 'arkhe.backend'],
    'avatar_disk' => env('ARKHE_AVATAR_DISK', 'public'),
    'avatar_path' => env('ARKHE_AVATAR_PATH', 'avatars'),
    'per_page' => 15,
    'user_model' => null,
    'permissions' => [],
    'roles' => [],
    'role_permissions' => ['root' => ['*']],
    'backend_permission' => 'access-backend',
    'root_permission' => 'manage-roles',
    'components' => [],
    'features' => ['cookie_consent' => false, 'seo' => false],
    'role_hierarchy' => [],
    'role_labels' => [],
];
PHP;
}
