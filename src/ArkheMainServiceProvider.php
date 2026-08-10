<?php

declare(strict_types=1);

namespace Arkhe\Main;

use Arkhe\Main\Commands\AddUserCommand;
use Arkhe\Main\Commands\InstallCommand;
use Arkhe\Main\Commands\UpgradeFromV2Command;
use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Contracts\SiteSeoRepositoryInterface;
use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Cookies\ArkheCookiesServiceProvider;
use Arkhe\Main\Http\Middleware\EnsureUserHasBackendAccess;
use Arkhe\Main\Http\Middleware\EnsureUserHasStrongAuth;
use Arkhe\Main\Http\Middleware\EnsureUserIsRoot;
use Arkhe\Main\Livewire\EditRole;
use Arkhe\Main\Livewire\EditUser;
use Arkhe\Main\Livewire\ListPermissions;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Livewire\Cookies;
use Arkhe\Main\Livewire\SiteSeo;
use Arkhe\Main\Livewire\Sitemap;
use Arkhe\Main\Livewire\StrongAuthRequired;
use Arkhe\Main\Jobs\GenerateSitemap;
use Arkhe\Main\Repositories\PermissionRepository;
use Arkhe\Main\Repositories\RoleRepository;
use Arkhe\Main\Repositories\SiteSeoRepository;
use Arkhe\Main\Repositories\UserRepository;
use Arkhe\Main\Services\SiteSeoService;
use Arkhe\Main\Support\ArkheNav;
use Arkhe\Main\Support\Features;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use RalphJSmit\Laravel\SEO\SEOManager;
use RalphJSmit\Laravel\SEO\Support\SEOData;
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
            ->hasMigration('create_arkhe_site_seo_table')
            ->hasMigration('add_sitemap_generated_at_to_arkhe_site_seo_table')
            ->hasCommand(InstallCommand::class)
            ->hasCommand(AddUserCommand::class)
            ->hasCommand(UpgradeFromV2Command::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(SiteSeoRepositoryInterface::class, SiteSeoRepository::class);

        // Pre-register Arkhe's CookiesServiceProvider so essentials (Laravel
        // session + CSRF) get listed automatically. Consumers can publish
        // their own via vendor:publish --tag=laravel-cookie-consent-service-provider
        // for app-specific cookies — both providers coexist.
        if (Features::hasCookieConsent() && class_exists(\Whitecube\LaravelCookieConsent\CookiesServiceProvider::class)) {
            $this->app->register(ArkheCookiesServiceProvider::class);
        }
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
        'edit-user'        => EditUser::class,
        'list-roles'       => ListRoles::class,
        'edit-role'        => EditRole::class,
        'list-permissions' => ListPermissions::class,
        'site-seo'         => SiteSeo::class,
        'sitemap'          => Sitemap::class,
        'cookies'          => Cookies::class,
        'strong-auth-required' => StrongAuthRequired::class,
    ];

    public function packageBooted(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('arkhe.backend',     EnsureUserHasBackendAccess::class);
        $router->aliasMiddleware('arkhe.root',        EnsureUserIsRoot::class);
        $router->aliasMiddleware('arkhe.strong-auth', EnsureUserHasStrongAuth::class);

        // Route middleware only guards the initial page load. Livewire's own
        // update endpoint carries just `['web']`, so every subsequent action —
        // saving a user, deleting a role — travels a path where none of the
        // gates above would otherwise run. Livewire re-applies a middleware on
        // those requests only if it is declared persistent.
        //
        // The two permission gates were never exposed by that gap because
        // every component re-checks with `$this->authorize()` on each action.
        // The strong-auth gate has no such per-action equivalent, so declaring
        // it here is what makes it real rather than a speed bump on the first
        // GET. Registered alongside the others so the whole stack behaves
        // consistently on both paths.
        Livewire::addPersistentMiddleware([
            EnsureUserHasBackendAccess::class,
            EnsureUserIsRoot::class,
            EnsureUserHasStrongAuth::class,
        ]);

        foreach (self::COMPONENT_DEFAULTS as $alias => $default) {
            $class = (string) config("arkhe.components.{$alias}", $default);
            Livewire::component("arkhe.{$alias}", $class);
        }

        $this->bootSeo();

        $this->bootSitemap();

        $this->bootFeatures();

        $this->registerDefaultNavigation();
    }

    /**
     * Seed the shared sidebar registry with Arkhè's own sections. Satellite
     * packages (e.g. arkhe-watcher) add their items/sections via
     * {@see ArkheNav} from their own service providers — see the registry's
     * class docblock. Rendered by `arkhe::partials.sidebar-items`.
     */
    private function registerDefaultNavigation(): void
    {
        $rootGate = static fn (?object $user): bool => ArkheNav::passes(
            (string) config('arkhe.root_permission', 'manage-roles'),
            $user,
        );

        ArkheNav::section('access', heading: static fn (): string => __('arkhe::arkhe.access.title'), priority: 10)
            ->item(
                key: 'users',
                label: static fn (): string => __('arkhe::arkhe.users.title'),
                icon: 'users',
                route: 'arkhe.users.index',
                active: 'arkhe.users.*',
                priority: 10,
            )
            // Permissions are managed from the roles page: a single entry
            // covers both (the permissions route redirects here).
            ->item(
                key: 'roles',
                label: static fn (): string => __('arkhe::arkhe.roles.title'),
                icon: 'key',
                route: 'arkhe.roles.index',
                active: static fn (): bool => request()->routeIs('arkhe.roles.*', 'arkhe.permissions.*'),
                can: $rootGate,
                priority: 20,
            );

        ArkheNav::section(
            'settings',
            heading: static fn (): string => __('arkhe::arkhe.settings.title'),
            priority: 80,
            can: $rootGate,
        )
            ->item(
                key: 'site-seo',
                label: static fn (): string => __('arkhe::arkhe.site_seo.title'),
                icon: 'globe-alt',
                route: 'arkhe.site-seo.edit',
                active: 'arkhe.site-seo.*',
                priority: 10,
            )
            ->item(
                key: 'sitemap',
                label: static fn (): string => __('arkhe::arkhe.sitemap.title'),
                icon: 'map',
                route: 'arkhe.sitemap.edit',
                active: 'arkhe.sitemap.*',
                priority: 20,
            )
            ->item(
                key: 'cookies',
                label: static fn (): string => __('arkhe::arkhe.cookies.title'),
                icon: 'cake',
                route: 'arkhe.cookies.index',
                active: 'arkhe.cookies.*',
                priority: 30,
            );
    }

    /**
     * Register a daily (configurable) job that regenerates the sitemap on
     * the host app's queue. The `manage-sitemap` Livewire page on
     * /administration/sitemap dispatches the same job on demand. Skipped
     * when `arkhe.sitemap.enabled` is false.
     */
    private function bootSitemap(): void
    {
        if (! (bool) config('arkhe.sitemap.enabled', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $expression = (string) config('arkhe.sitemap.schedule', '0 3 * * *');

            $schedule->job(new GenerateSitemap)
                ->cron($expression)
                ->name('arkhe-main:sitemap')
                ->onOneServer();
        });
    }

    /**
     * Register a SEOData transformer that merges Arkhe's site-wide SEO
     * settings into the SEOData rendered by every page. Guarded so the
     * transformer is a no-op if the table is missing (early install).
     */
    private function bootSeo(): void
    {
        if (! Features::hasSeo()) {
            return;
        }

        if (! class_exists(SEOManager::class)) {
            return;
        }

        $this->app->afterResolving(SEOManager::class, function (SEOManager $manager): void {
            $manager->SEODataTransformer(function (SEOData $data): SEOData {
                return $this->app->make(SiteSeoService::class)->applyTo($data);
            });
        });
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
