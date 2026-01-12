<?php

namespace Arkhe\Main\Tests;

use Arkhe\Main\ArkheMainServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

// Load fixtures
require_once __DIR__.'/Fixtures/User.php';
require_once __DIR__.'/Fixtures/UserFactory.php';

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ArkheMainServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        $app['config']->set('permission.column_names', [
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load test migrations first (creates users table)
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        // Then load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../stubs/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create base roles needed for tests
        $this->createBaseRoles();

        // Define routes needed for Livewire component tests
        $this->defineTestRoutes();
    }

    private function defineTestRoutes(): void
    {
        \Illuminate\Support\Facades\Route::get('/admin/users', fn () => 'users')->name('admin.users.index');
        \Illuminate\Support\Facades\Route::get('/admin/users/create', fn () => 'create')->name('admin.users.create');
        \Illuminate\Support\Facades\Route::get('/admin/users/{user}/edit', fn () => 'edit')->name('admin.users.edit');
        \Illuminate\Support\Facades\Route::get('/admin/roles', fn () => 'roles')->name('admin.users.roles.index');
        \Illuminate\Support\Facades\Route::get('/admin/roles/create', fn () => 'create')->name('admin.users.roles.edit');
        \Illuminate\Support\Facades\Route::get('/admin/dashboard', fn () => 'dashboard')->name('admin.dashboard');
    }

    private function createBaseRoles(): void
    {
        $roles = ['root', 'admin', 'editorial', 'author', 'contributor', 'subscriber', 'guest'];

        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => $role, 'guard_name' => 'web'],
                ['label' => ucfirst($role)]
            );
        }
    }
}
