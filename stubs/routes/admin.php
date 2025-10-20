<?php

declare(strict_types=1);

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware([
    'auth',
    'verified',
    'role:'.
        UserRoleEnum::ROOT->value.'|'.
        UserRoleEnum::ADMIN->value.'|'.
        UserRoleEnum::EDITORIAL->value.'|'.
        UserRoleEnum::AUTHOR->value.'|'.
        UserRoleEnum::CONTRIBUTOR->value,
])
    ->prefix(config('arkhe.admin.prefix'))
    ->name('admin.')
    ->group(function () {
        /**
         * Laravel Settings
         */
        Route::prefix('settings')
            ->name('settings.')
            ->group(function () {

                Route::redirect('settings', 'profile');

                Route::get('profile', Profile::class)->name('profile'); // admin.settings.profile
                Route::get('password', Password::class)->name('password'); // admin.settings.password
                Route::get('appearance', Appearance::class)->name('appearance'); // admin.settings.appearance

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
