<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Tests\Stubs\User;
use Spatie\Permission\Models\Role;

it('seeds the four arkhe roles', function (): void {
    expect(Role::query()->count())->toBe(0);

    $this->app->make(ArkheRolesSeeder::class)->run();

    expect(Role::query()->pluck('name')->all())
        ->toContain('root', 'administrateur', 'user', 'guest');

    foreach (Role::query()->get() as $role) {
        expect($role->guard_name)->toBe('web');
    }
});

it('is idempotent — seeding twice does not duplicate roles', function (): void {
    $seeder = $this->app->make(ArkheRolesSeeder::class);
    $seeder->run();
    $seeder->run();

    expect(Role::query()->count())->toBe(4);
});

it('exposes the profile columns added by the migration stub', function (): void {
    $columns = ['first_name', 'last_name', 'avatar_path', 'phone', 'date_of_birth', 'civility', 'bio'];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue("Missing column {$column}");
    }
});

it('creates a root user with the root role assigned', function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Luc',
        'last_name'  => 'Adhocrat',
        'email'      => 'luc@adhocrat.io',
        'password'   => bcrypt('password123'),
    ]);

    $user->assignRole(config('arkhe.roles.root'));

    expect($user->fresh()->hasRole('root'))->toBeTrue();
    expect($user->isArkheRoot())->toBeTrue();
});
