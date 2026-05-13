<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe;

use Adhocrat\Arkhe\Commands\InstallCommand;
use Adhocrat\Arkhe\Contracts\PermissionRepositoryInterface;
use Adhocrat\Arkhe\Contracts\RoleRepositoryInterface;
use Adhocrat\Arkhe\Contracts\UserRepositoryInterface;
use Adhocrat\Arkhe\Http\Middleware\EnsureUserHasBackendAccess;
use Adhocrat\Arkhe\Http\Middleware\EnsureUserIsRoot;
use Adhocrat\Arkhe\Livewire\Dashboard;
use Adhocrat\Arkhe\Livewire\ListPermissions;
use Adhocrat\Arkhe\Livewire\ListRoles;
use Adhocrat\Arkhe\Livewire\ListUsers;
use Adhocrat\Arkhe\Repositories\PermissionRepository;
use Adhocrat\Arkhe\Repositories\RoleRepository;
use Adhocrat\Arkhe\Repositories\UserRepository;
use Adhocrat\Arkhe\Support\Features;
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
            ->hasMigration('add_arkhe_profile_columns_to_users_table')
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
    }

    public function packageBooted(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('arkhe.backend', EnsureUserHasBackendAccess::class);
        $router->aliasMiddleware('arkhe.root',    EnsureUserIsRoot::class);

        Livewire::component('arkhe.list-users',       ListUsers::class);
        Livewire::component('arkhe.list-roles',       ListRoles::class);
        Livewire::component('arkhe.list-permissions', ListPermissions::class);
        Livewire::component('arkhe.dashboard',        Dashboard::class);

        $this->overrideFortifyHome();

        $this->bootFeatures();
    }

    private function overrideFortifyHome(): void
    {
        if (! (bool) config('arkhe.override_fortify_redirect', true)) {
            return;
        }

        $dashboard = (string) config('arkhe.dashboard_route', '');
        if ($dashboard === '') {
            return;
        }

        // Only act when Fortify is actually wired up — Arkhe doesn't depend on it.
        if (! interface_exists(\Laravel\Fortify\Contracts\LoginResponse::class)) {
            return;
        }

        $path = '/'.ltrim($dashboard, '/');
        config(['fortify.home' => $path]);
    }

    private function bootFeatures(): void
    {
        if (Features::hasCookieConsent() && class_exists(\Spatie\CookieConsent\CookieConsentServiceProvider::class)) {
            // Phase 2: cookie consent wire-up.
        }

        if (Features::hasSeo() && class_exists(\RalphJSmit\Laravel\SEO\SEOServiceProvider::class)) {
            // Phase 2: SEO wire-up.
        }
    }
}
