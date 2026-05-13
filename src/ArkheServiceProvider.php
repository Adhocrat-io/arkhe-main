<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe;

use Adhocrat\Arkhe\Contracts\UserRepositoryInterface;
use Adhocrat\Arkhe\Http\Middleware\EnsureUserHasBackendAccess;
use Adhocrat\Arkhe\Livewire\ListUsers;
use Adhocrat\Arkhe\Repositories\UserRepository;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ArkheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arkhe')
            ->hasConfigFile('arkhe')
            ->hasViews('arkhe')
            ->hasTranslations()
            ->hasRoute('arkhe')
            ->hasMigration('add_arkhe_profile_columns_to_users_table');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function packageBooted(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('arkhe.backend', EnsureUserHasBackendAccess::class);

        Livewire::component('arkhe::list-users', ListUsers::class);
    }
}
