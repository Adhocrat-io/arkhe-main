<?php

declare(strict_types=1);

use Adhocrat\Arkhe\Livewire\ListUsers;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('arkhe.route_prefix'))
    ->middleware((array) config('arkhe.middleware'))
    ->name('arkhe.')
    ->group(function (): void {
        Route::get('/users', ListUsers::class)->name('users.index');
    });
