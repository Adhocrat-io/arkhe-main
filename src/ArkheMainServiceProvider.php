<?php

declare(strict_types=1);

namespace Arkhe\Main;

use Arkhe\Main\Commands\AddUserCommand;
use Arkhe\Main\Commands\InstallCommand;
use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Http\Middleware\EnsureUserHasBackendAccess;
use Arkhe\Main\Http\Middleware\EnsureUserIsRoot;
use Arkhe\Main\Livewire\Dashboard;
use Arkhe\Main\Livewire\ListPermissions;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Repositories\PermissionRepository;
use Arkhe\Main\Repositories\RoleRepository;
use Arkhe\Main\Repositories\UserRepository;
use Arkhe\Main\Support\Features;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ArkheMainServiceProvider extends PackageServiceProvider
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
            ->hasCommand(InstallCommand::class)
            ->hasCommand(AddUserCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
    }

    /**
     * Default mapping for Livewire components. Each entry can be swapped via
     * `config('arkhe.components.<alias>')` to point at a host-app subclass —
     * see notes/2026-05-20-extensibility-and-revel-upgrade.md for the pattern.
     *
     * @var array<string, class-string>
     */
    public const COMPONENT_DEFAULTS = [
        'list-users'       => ListUsers::class,
        'list-roles'       => ListRoles::class,
        'list-permissions' => ListPermissions::class,
        'dashboard'        => Dashboard::class,
    ];

    public function packageBooted(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('arkhe.backend', EnsureUserHasBackendAccess::class);
        $router->aliasMiddleware('arkhe.root',    EnsureUserIsRoot::class);

        foreach (self::COMPONENT_DEFAULTS as $alias => $default) {
            $class = (string) config("arkhe.components.{$alias}", $default);
            Livewire::component("arkhe.{$alias}", $class);
        }

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
