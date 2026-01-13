<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

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

it('shows non-interactive mode message with -y option', function () {
    config(['app.locale' => 'en']);

    try {
        Artisan::call('arkhe:main:install', ['--yes' => true]);
    } catch (Throwable) {
        // Command may fail due to missing seeders in test environment
    }

    $output = Artisan::output();

    expect($output)->toContain('Running in non-interactive mode');
    expect($output)->toContain('Installing Arkhe Main package');
    expect($output)->not->toContain('Do you want to publish the configuration?');
});

it('shows french non-interactive message with locale fr', function () {
    config(['app.locale' => 'fr']);

    try {
        Artisan::call('arkhe:main:install', ['--yes' => true]);
    } catch (Throwable) {
        // Command may fail due to missing seeders in test environment
    }

    $output = Artisan::output();

    expect($output)->toContain('Exécution en mode non-interactif');
    expect($output)->toContain('Installation du package Arkhe Main');
});

it('has correct command signature with options', function () {
    $command = Artisan::all()['arkhe:main:install'];
    $definition = $command->getDefinition();

    $yesOption = $definition->getOption('yes');

    expect($yesOption)->not->toBeNull();
    expect($yesOption->getShortcut())->toBe('y');
    expect($definition->hasOption('force'))->toBeTrue();
    expect($definition->hasOption('with-test-users'))->toBeTrue();
});
