<?php

declare(strict_types=1);

use Arkhe\Main\Livewire\Dashboard;
use Arkhe\Main\Livewire\ListPermissions;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Livewire\SiteSeo;
use Illuminate\Support\Facades\Route;

$middleware = (array) config('arkhe.middleware');

// Resolve every Livewire page from the `arkhe.components` map so a host app
// can route to its own subclass without redeclaring any of these routes.
// Falls back to the package's default class when no override is set.
$component = static fn (string $alias, string $default): string => (string) config("arkhe.components.{$alias}", $default);

// Top-level dashboard route (opt-in via ARKHE_DASHBOARD_ROUTE).
// Registered outside the `arkhe.` name group so its full name is
// configurable (defaults to `arkhe.dashboard`, can be set to `dashboard`
// so the starter kit's after-login `route('dashboard')` resolves here).
if ($dashboardPath = config('arkhe.dashboard_route')) {
    Route::middleware($middleware)
        ->get('/'.ltrim((string) $dashboardPath, '/'), $component('dashboard', Dashboard::class))
        ->name((string) config('arkhe.dashboard_route_name', 'arkhe.dashboard'));
}

Route::middleware($middleware)
    ->prefix((string) config('arkhe.admin.prefix', config('arkhe.route_prefix', 'administration')))
    ->name('arkhe.')
    ->group(function () use ($component): void {
        Route::get('/users', $component('list-users', ListUsers::class))->name('users.index');

        // Roles + permissions + site SEO are restricted to the `root` role.
        Route::middleware('arkhe.root')->group(function () use ($component): void {
            Route::get('/roles',       $component('list-roles', ListRoles::class))->name('roles.index');
            Route::get('/permissions', $component('list-permissions', ListPermissions::class))->name('permissions.index');
            Route::get('/seo',         $component('site-seo',   SiteSeo::class))->name('site-seo.edit');
        });
    });
