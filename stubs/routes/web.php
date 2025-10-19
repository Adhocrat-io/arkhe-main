<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::name('front.')
    ->group(function () {
        Route::get('/', function () {
            return view('welcome');
        })->name('home');
    });

require __DIR__.'/admin.php';
