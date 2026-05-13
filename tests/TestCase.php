<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Tests;

use Adhocrat\Arkhe\ArkheServiceProvider;
use Adhocrat\Arkhe\Tests\Stubs\User;
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
            ArkheServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
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
    }

    protected function setUpDatabase(): void
    {
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

        // Spatie permission tables.
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }
}
