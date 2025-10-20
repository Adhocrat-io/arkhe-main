<?php

declare(strict_types=1);

namespace Arkhe\Main;

use Arkhe\Main\Console\Commands\InstallCommand;
use Arkhe\Main\Livewire\Admin\Users\UserCreate;
use Arkhe\Main\Livewire\Admin\Users\UserEdit;
use Arkhe\Main\Livewire\Admin\Users\UsersList;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ArkheMainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/arkhe.php', 'arkhe');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/arkhe.php' => config_path('arkhe.php'),
        ], 'arkhe-main-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'arkhe-main-migrations');

        $this->publishes([
            __DIR__.'/../database/factories/UserFactory.php' => database_path('factories/UserFactory.php'),
            __DIR__.'/../database/seeders/DatabaseSeeder.php' => database_path('seeders/DatabaseSeeder.php'),
            __DIR__.'/../database/seeders/RolesAndPermissionsSeeder.php' => database_path('seeders/RolesAndPermissionsSeeder.php'),
            __DIR__.'/../database/seeders/TestUsersSeeder.php' => database_path('seeders/TestUsersSeeder.php'),
        ], 'arkhe-main-roles-seeder');

        $this->publishes([
            __DIR__.'/../stubs/bootstrap/app.php' => base_path('bootstrap/app.php'),

            __DIR__.'/../stubs/routes/web.php' => base_path('routes/web.php'),
            __DIR__.'/../stubs/routes/admin.php' => base_path('routes/admin.php'),

            __DIR__.'/../stubs/Models/User.php' => app_path('Models/User.php'),

            __DIR__.'/../stubs/app/Http/Controllers/Auth/VerifyEmailController.php' => app_path('Http/Controllers/Auth/VerifyEmailController.php'),
            __DIR__.'/../stubs/app/Livewire/Auth/Register.php' => app_path('Livewire/Auth/Register.php'),
            __DIR__.'/../stubs/app/Livewire/Auth/Login.php' => app_path('Livewire/Auth/Login.php'),
            __DIR__.'/../stubs/app/Livewire/Auth/VerifyEmail.php' => app_path('Livewire/Auth/VerifyEmail.php'),
            __DIR__.'/../stubs/app/Livewire/Settings/Profile.php' => app_path('Livewire/Settings/Profile.php'),

            __DIR__.'/../stubs/resources/views/livewire/auth/register.blade.php' => resource_path('views/livewire/auth/register.blade.php'),
            __DIR__.'/../stubs/resources/views/livewire/auth/login.blade.php' => resource_path('views/livewire/auth/login.blade.php'),
            __DIR__.'/../stubs/resources/views/livewire/settings/profile.blade.php' => resource_path('views/livewire/settings/profile.blade.php'),
            __DIR__.'/../stubs/resources/views/components/layouts/app/sidebar.blade.php' => resource_path('views/components/layouts/app/sidebar.blade.php'),
            __DIR__.'/../stubs/resources/views/components/layouts/app/header.blade.php' => resource_path('views/components/layouts/app/header.blade.php'),
            __DIR__.'/../stubs/resources/views/components/settings/layout.blade.php' => resource_path('views/components/settings/layout.blade.php'),
            __DIR__.'/../stubs/resources/views/components/layouts/auth/card.blade.php' => resource_path('views/components/layouts/auth/card.blade.php'),
            __DIR__.'/../stubs/resources/views/components/layouts/auth/split.blade.php' => resource_path('views/components/layouts/auth/split.blade.php'),
            __DIR__.'/../stubs/resources/views/components/layouts/auth/simple.blade.php' => resource_path('views/components/layouts/auth/simple.blade.php'),

            __DIR__.'/../stubs/tests/TestCase.php' => base_path('tests/TestCase.php'),
            __DIR__.'/../stubs/tests/DashboardTest.php' => base_path('tests/Feature/DashboardTest.php'),
            __DIR__.'/../stubs/tests/Auth/AuthenticationTest.php' => base_path('tests/Feature/Auth/AuthenticationTest.php'),
            __DIR__.'/../stubs/tests/Auth/EmailVerificationTest.php' => base_path('tests/Feature/Auth/EmailVerificationTest.php'),
            __DIR__.'/../stubs/tests/Auth/RegistrationTest.php' => base_path('tests/Feature/Auth/RegistrationTest.php'),
            __DIR__.'/../stubs/tests/Auth/TwoFactorChallengeTest.php' => base_path('tests/Feature/Auth/TwoFactorChallengeTest.php'),
            __DIR__.'/../stubs/tests/Settings/ProfileUpdateTest.php' => base_path('tests/Feature/Settings/ProfileUpdateTest.php'),
            __DIR__.'/../stubs/tests/Settings/TwoFactorAuthenticationTest.php' => base_path('tests/Feature/Settings/TwoFactorAuthenticationTest.php'),
        ], 'arkhe-main-files');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'arkhe-main');

        Livewire::component('arkhe.main.livewire.admin.users.users-list', UsersList::class);
        Livewire::component('arkhe.main.livewire.admin.users.users-create', UserCreate::class);
        Livewire::component('arkhe.main.livewire.admin.users.users-edit', UserEdit::class);

        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/arkhe-main'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
