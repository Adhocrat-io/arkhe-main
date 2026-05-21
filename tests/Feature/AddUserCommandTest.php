<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Tests\Stubs\User;

beforeEach(fn () => $this->app->make(ArkheRolesSeeder::class)->run());

it('creates a user from CLI options without any prompt', function (): void {
    $this->artisan('arkhe:main:add-user', [
        '--email'    => 'cli-user@x.test',
        '--first'    => 'Cli',
        '--last'     => 'User',
        '--role'     => 'administrateur',
        '--password' => 'secret123',
    ])->assertSuccessful();

    $u = User::query()->where('email', 'cli-user@x.test')->first();
    expect($u)->not->toBeNull();
    expect($u->hasRole('administrateur'))->toBeTrue();
});

it('rejects an unknown role', function (): void {
    $this->artisan('arkhe:main:add-user', [
        '--email'    => 'bad-role@x.test',
        '--first'    => 'X',
        '--last'     => 'Y',
        '--role'     => 'sysadmin',
        '--password' => 'secret123',
    ])->assertFailed();

    expect(User::query()->where('email', 'bad-role@x.test')->first())->toBeNull();
});

it('drives the interactive prompts when options are omitted', function (): void {
    $this->artisan('arkhe:main:add-user')
        ->expectsQuestion('Email', 'prompted@x.test')
        ->expectsQuestion('First name', 'Pr')
        ->expectsQuestion('Last name', 'Mp')
        ->expectsQuestion('Role', 'user')
        ->expectsQuestion('Password', 'secret123')
        ->expectsQuestion('Confirm password', 'secret123')
        ->assertSuccessful();

    $u = User::query()->where('email', 'prompted@x.test')->first();
    expect($u)->not->toBeNull();
    expect($u->hasRole('user'))->toBeTrue();
});

it('does not enforce the role hierarchy when running from CLI', function (): void {
    // No auth context — the service's rank check would normally reject `root`
    // (rank 3) for an actor at rank -1 (no actor). The CLI bypass lets it
    // through.
    $this->artisan('arkhe:main:add-user', [
        '--email'    => 'cli-root@x.test',
        '--first'    => 'Cli',
        '--last'     => 'Root',
        '--role'     => 'root',
        '--password' => 'secret123',
    ])->assertSuccessful();

    expect(User::query()->where('email', 'cli-root@x.test')->first()->hasRole('root'))
        ->toBeTrue();
});
