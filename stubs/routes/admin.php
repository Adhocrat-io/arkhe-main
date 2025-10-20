<?php

declare(strict_types=1);

use App\Livewire\Settings;
use App\Livewire\Settings\TwoFactor;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Livewire\Admin\Users;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::prefix(config('arkhe.admin.prefix'))
    ->name('admin.')
    ->middleware([
        'auth',
        'verified',
        'role:'.
            UserRoleEnum::ROOT->value.'|'.
            UserRoleEnum::ADMIN->value.'|'.
            UserRoleEnum::EDITORIAL->value.'|'.
            UserRoleEnum::AUTHOR->value.'|'.
            UserRoleEnum::CONTRIBUTOR->value,
    ])
    ->group(function () {
        /**
         * Users & Roles
         */
        Route::name('users.')
            ->middleware(
                'role:'.
                    UserRoleEnum::ROOT->value.'|'.
                    UserRoleEnum::ADMIN->value
            )
            ->group(function () {
                Route::get('users/', Users\UsersList::class)->name('index'); // admin.users.index
                Route::get('users/create', Users\UserCreate::class)->name('create'); // admin.users.create
                Route::get('users/edit/{user}', Users\UserEdit::class)->name('edit'); // admin.users.edit

                Route::middleware('role:'.UserRoleEnum::ROOT->value)
                    ->name('roles.')
                    ->group(function () {
                        Route::get('users/roles', function () {})->name('index'); // admin.users.roles.index
                        Route::get('users/roles/create', function () {})->name('create'); // admin.users.roles.create
                        Route::get('users/roles/edit/{role}', function () {})->name('edit'); // admin.users.roles.edit
                    });
            });

        /**
         * Laravel Settings
         */
        Route::prefix('settings')
            ->name('settings.')
            ->group(function () {

                Route::redirect('settings', 'profile');

                Route::get('profile', Settings\Profile::class)->name('profile'); // admin.settings.profile
                Route::get('password', Settings\Password::class)->name('password'); // admin.settings.password
                Route::get('appearance', Settings\Appearance::class)->name('appearance'); // admin.settings.appearance

                Route::get('two-factor', TwoFactor::class)
                    ->middleware(
                        when(
                            Features::canManageTwoFactorAuthentication()
                                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                            ['password.confirm'],
                            [],
                        ),
                    )
                    ->name('two-factor.show'); // admin.settings.two-factor.show
            });

        /**
         * Dashboard
         */
        Route::view('/dashboard', 'dashboard')->name('dashboard'); // admin.dashboard
        Route::redirect('/', '/'.config('arkhe.admin.prefix').'/dashboard');
    });

require __DIR__.'/auth.php';
