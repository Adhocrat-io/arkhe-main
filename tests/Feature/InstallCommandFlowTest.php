<?php

declare(strict_types=1);

use Arkhe\Main\Tests\Stubs\User;
use Spatie\Permission\Models\Role;

it('runs end-to-end without prompts, seeding roles and creating the root user', function (): void {
    // Spatie tables already exist via TestCase; just exercise the command.
    $this->artisan('arkhe:main:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Publish the migrations?', 'no')
        ->expectsConfirmation('Publish the views? (optional)', 'no')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->expectsConfirmation('Add the Arkhe links to your sidebar automatically?', 'no')
        ->expectsConfirmation('Create the first root user?', 'yes')
        ->expectsQuestion('Root user email', 'root@cli.test')
        ->expectsQuestion('Root user password', 'secret123')
        ->expectsQuestion('Confirm password', 'secret123')
        ->expectsQuestion('First name', 'Luc')
        ->expectsQuestion('Last name', 'Adhocrat')
        ->assertSuccessful();

    expect(Role::query()->where('name', 'root')->first())->not->toBeNull();

    $root = User::query()->where('email', 'root@cli.test')->first();
    expect($root)->not->toBeNull();
    expect($root->hasRole('root'))->toBeTrue();
});
