<?php

declare(strict_types=1);

use Adhocrat\Arkhe\Database\Seeders\ArkheRolesSeeder;
use Adhocrat\Arkhe\Tests\Stubs\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

it('exposes full_name from first_name and last_name', function (): void {
    $user = User::query()->forceCreate([
        'first_name' => 'Luc',
        'last_name'  => 'Adhocrat',
        'email'      => 'luc@adhocrat.io',
        'password'   => 'secret',
    ]);

    expect($user->full_name)->toBe('Luc Adhocrat');
});

it('trims full_name when one side is missing', function (): void {
    $user = User::query()->forceCreate([
        'first_name' => 'Luc',
        'email'      => 'luc-only@adhocrat.io',
        'password'   => 'secret',
    ]);

    expect($user->full_name)->toBe('Luc');
});

it('returns null avatar_url when no avatar_path is stored', function (): void {
    $user = User::query()->forceCreate([
        'email'    => 'noavatar@example.test',
        'password' => 'secret',
    ]);

    expect($user->avatar_url)->toBeNull();
});

it('returns a storage url when avatar_path is set', function (): void {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $path = $disk->putFile('avatars', UploadedFile::fake()->image('a.jpg'));

    $user = User::query()->forceCreate([
        'avatar_path' => $path,
        'email'       => 'avatar@example.test',
        'password'    => 'secret',
    ]);

    expect($user->avatar_url)->toBeString();
});

it('builds uppercase initials from first and last name', function (): void {
    $user = User::query()->forceCreate([
        'first_name' => 'luc',
        'last_name'  => 'adhocrat',
        'email'      => 'init@example.test',
        'password'   => 'secret',
    ]);

    expect($user->initials)->toBe('LA');
});

it('detects the root role via isArkheRoot', function (): void {
    $user = User::query()->forceCreate([
        'email'    => 'root@example.test',
        'password' => 'secret',
    ]);
    $user->assignRole(config('arkhe.roles.root'));

    expect($user->isArkheRoot())->toBeTrue();
    expect($user->isArkheAdmin())->toBeTrue();
});

it('detects the administrateur role via isArkheAdmin only', function (): void {
    $user = User::query()->forceCreate([
        'email'    => 'admin@example.test',
        'password' => 'secret',
    ]);
    $user->assignRole(config('arkhe.roles.administrator'));

    expect($user->isArkheRoot())->toBeFalse();
    expect($user->isArkheAdmin())->toBeTrue();
});

it('returns false for a non-privileged user', function (): void {
    $user = User::query()->forceCreate([
        'email'    => 'plain@example.test',
        'password' => 'secret',
    ]);
    $user->assignRole(config('arkhe.roles.user'));

    expect($user->isArkheRoot())->toBeFalse();
    expect($user->isArkheAdmin())->toBeFalse();
});
