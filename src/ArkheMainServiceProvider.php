<?php

declare(strict_types=1);

namespace Arkhe\Main;

use Arkhe\Main\Console\Commands\InstallCommand;
use Arkhe\Main\Livewire\Admin\Users\Roles\RoleEdit;
use Arkhe\Main\Livewire\Admin\Users\Roles\RolesList;
use Arkhe\Main\Livewire\Admin\Users\UserCreate;
use Arkhe\Main\Livewire\Admin\Users\UserEdit;
use Arkhe\Main\Livewire\Admin\Users\UsersList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ArkheMainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // $this->mergeConfigFrom(__DIR__.'/../config/arkhe.php', 'arkhe');
    }

    public function boot(): void
    {
        $this->configureFortifyRedirects();

        $this->publishes(
            [__DIR__ . '/../config/arkhe.php' => config_path('arkhe.php')],
            'arkhe-main-config'
        );

        $this->publishes(
            [__DIR__ . '/../stubs/database/migrations' => database_path('migrations')],
            'arkhe-main-migrations'
        );

        $this->publishes(
            [
                __DIR__ . '/../stubs/database/seeders' => database_path('seeders'),
                __DIR__ . '/../stubs/database/factories' => database_path('factories'),
            ],
            'arkhe-main-roles-seeder'
        );

        $this->publishes(
            [
                __DIR__ . '/../stubs/bootstrap/app.php' => base_path('bootstrap/app.php'),
                __DIR__ . '/../stubs/routes' => base_path('routes'),

                __DIR__ . '/../stubs/Models/' => app_path('Models/'),
                __DIR__ . '/../stubs/app/Http/Controllers/' => app_path('Http/Controllers/'),
                __DIR__ . '/../stubs/app/Livewire/' => app_path('Livewire/'),

                __DIR__ . '/../stubs/resources/views/livewire/' => resource_path('views/livewire/'),
                __DIR__ . '/../stubs/resources/views/components/' => resource_path('views/components/'),

                __DIR__ . '/../stubs/tests/' => base_path('tests/'),
            ],
            'arkhe-main-files'
        );

        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'arkhe-main'
        );

        Livewire::component('arkhe.main.livewire.admin.users.users-list', UsersList::class);
        Livewire::component('arkhe.main.livewire.admin.users.users-create', UserCreate::class);
        Livewire::component('arkhe.main.livewire.admin.users.users-edit', UserEdit::class);
        Livewire::component('arkhe.main.livewire.admin.users.roles.roles-list', RolesList::class);
        Livewire::component('arkhe.main.livewire.admin.users.roles.role-edit', RoleEdit::class);

        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->publishes(
            [__DIR__ . '/../lang' => $this->app->langPath('vendor/arkhe-main')],
            'arkhe-main-lang'
        );

        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    InstallCommand::class
                ]
            );
        }
    }

    private function configureFortifyRedirects(): void
    {
        $loginResponseClass = 'Laravel\Fortify\Contracts\LoginResponse';
        $logoutResponseClass = 'Laravel\Fortify\Contracts\LogoutResponse';

        if (interface_exists($loginResponseClass)) {
            $this->app->singleton($loginResponseClass, function () {
                return new class implements \Laravel\Fortify\Contracts\LoginResponse {
                    public function toResponse($request): RedirectResponse
                    {
                        return redirect()->route('admin.dashboard');
                    }
                };
            });
        }

        if (interface_exists($logoutResponseClass)) {
            $this->app->singleton($logoutResponseClass, function () {
                return new class implements \Laravel\Fortify\Contracts\LogoutResponse {
                    public function toResponse($request): RedirectResponse
                    {
                        return redirect()->route('login');
                    }
                };
            });
        }
    }
}
