<?php

declare(strict_types=1);

it('uses the configured locale for messages', function () {
    config(['app.locale' => 'fr']);

    $this->artisan('arkhe:main:install')
        ->expectsOutputToContain('Installation du package Arkhe Main')
        ->expectsConfirmation(__('Do you want to publish the configuration?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the migrations?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the roles and permissions seeder?'), 'no')
        ->expectsConfirmation(__('Do you want to run the migrations?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the lang files?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the modified files?'), 'no')
        ->expectsConfirmation(__('Do you want to run the roles and permissions seeder?'), 'no')
        ->expectsConfirmation(__("Do you want to create test users (don't do this on production)?"), 'no')
        ->assertSuccessful();
});

it('uses english locale by default', function () {
    config(['app.locale' => 'en']);

    $this->artisan('arkhe:main:install')
        ->expectsOutputToContain('Installing Arkhe Main package')
        ->expectsConfirmation(__('Do you want to publish the configuration?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the migrations?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the roles and permissions seeder?'), 'no')
        ->expectsConfirmation(__('Do you want to run the migrations?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the lang files?'), 'no')
        ->expectsConfirmation(__('Do you want to publish the modified files?'), 'no')
        ->expectsConfirmation(__('Do you want to run the roles and permissions seeder?'), 'no')
        ->expectsConfirmation(__("Do you want to create test users (don't do this on production)?"), 'no')
        ->assertSuccessful();
});
