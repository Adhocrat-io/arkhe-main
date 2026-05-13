<?php

declare(strict_types=1);

use Adhocrat\Arkhe\Livewire\Dashboard;
use Adhocrat\Arkhe\Livewire\ListUsers;
use Illuminate\Support\Facades\Route;

Route::middleware((array) config('arkhe.middleware'))
    ->name('arkhe.')
    ->group(function (): void {
        if ($dashboard = config('arkhe.dashboard_route')) {
            Route::get('/'.ltrim((string) $dashboard, '/'), Dashboard::class)
                ->name('dashboard');
        }

        Route::prefix((string) config('arkhe.route_prefix'))
            ->group(function (): void {
                Route::get('/users', ListUsers::class)->name('users.index');
            });
    });
