<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests;

use Arkhe\Main\ArkheMainServiceProvider;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            PermissionServiceProvider::class,
            \RalphJSmit\Laravel\SEO\LaravelSEOServiceProvider::class,
            ArkheMainServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root'   => storage_path('framework/testing/disks/local'),
        ]);
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root'   => storage_path('framework/testing/disks/public'),
            'url'    => 'http://localhost/storage',
        ]);

        $app['config']->set('arkhe.avatar_disk', 'local');

        // A stub login route so the auth middleware can redirect anonymous
        // visitors during HTTP tests instead of throwing RouteNotFoundException.
        $app['router']->get('/login', fn () => 'login')->name('login');
    }

    protected function setUpDatabase(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('civility', 32)->nullable();
            $table->text('bio')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $this->setUpSpatiePermissionTables();

        Schema::create('arkhe_site_seo', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name')->nullable();
            $table->string('title_suffix')->nullable();
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('image')->nullable();
            $table->string('robots')->nullable();
            $table->string('twitter_username')->nullable();
            $table->string('favicon')->nullable();
            $table->timestamp('sitemap_generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seo', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->longText('description')->nullable();
            $table->string('title')->nullable();
            $table->string('image')->nullable();
            $table->string('author')->nullable();
            $table->string('robots')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Mirror of spatie/laravel-permission's create_permission_tables.php.stub.
     * Hand-rolled here because the package ships its migrations as .php.stub
     * files (unsuitable for loadMigrationsFrom), and Testbench has no clean
     * way to publish + run them in-memory.
     */
    protected function setUpSpatiePermissionTables(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
}
