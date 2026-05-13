<?php

declare(strict_types=1);

use Adhocrat\Arkhe\Livewire\Dashboard;
use Adhocrat\Arkhe\Livewire\ListPermissions;
use Adhocrat\Arkhe\Livewire\ListRoles;
use Adhocrat\Arkhe\Livewire\ListUsers;
use Illuminate\Support\Facades\Route;

$middleware = (array) config('arkhe.middleware');

// Top-level dashboard route (opt-in via ARKHE_DASHBOARD_ROUTE).
// Registered outside the `arkhe.` name group so its full name is
// configurable (defaults to `arkhe.dashboard`, can be set to `dashboard`
// so the starter kit's after-login `route('dashboard')` resolves here).
if ($dashboardPath = config('arkhe.dashboard_route')) {
    Route::middleware($middleware)
        ->get('/'.ltrim((string) $dashboardPath, '/'), Dashboard::class)
        ->name((string) config('arkhe.dashboard_route_name', 'arkhe.dashboard'));
}

Route::middleware($middleware)
    ->prefix((string) config('arkhe.route_prefix'))
    ->name('arkhe.')
    ->group(function (): void {
        Route::get('/users', ListUsers::class)->name('users.index');

        // Roles + permissions management is restricted to the `root` role.
        Route::middleware('arkhe.root')->group(function (): void {
            Route::get('/roles',       ListRoles::class)->name('roles.index');
            Route::get('/permissions', ListPermissions::class)->name('permissions.index');
        });
    });
