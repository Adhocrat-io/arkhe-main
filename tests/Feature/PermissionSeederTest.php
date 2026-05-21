<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->app->make(ArkheRolesSeeder::class)->run());

it('seeds every permission declared in config', function (): void {
    /** @var array<int, string> $expected */
    $expected = config('arkhe.permissions');

    foreach ($expected as $name) {
        expect(Permission::query()->where('name', $name)->where('guard_name', 'web')->exists())
            ->toBeTrue("Missing permission {$name}");
    }
});

it('grants every permission to the root role via the wildcard mapping', function (): void {
    $root = Role::query()->where('name', 'root')->where('guard_name', 'web')->firstOrFail();

    /** @var array<int, string> $allPermissions */
    $allPermissions = config('arkhe.permissions');

    foreach ($allPermissions as $name) {
        expect($root->hasPermissionTo($name, 'web'))->toBeTrue("Root missing {$name}");
    }
});

it('grants the administrateur role only the user-management permissions', function (): void {
    $admin = Role::query()->where('name', 'administrateur')->where('guard_name', 'web')->firstOrFail();

    foreach (['access-backend', 'manage-users', 'view-user', 'create-user', 'update-user', 'delete-user'] as $name) {
        expect($admin->hasPermissionTo($name, 'web'))->toBeTrue("Admin missing {$name}");
    }

    foreach (['manage-roles', 'manage-permissions', 'delete-role', 'create-permission'] as $name) {
        expect($admin->hasPermissionTo($name, 'web'))->toBeFalse("Admin should NOT have {$name}");
    }
});

it('does not grant any permission to the user and guest roles', function (): void {
    foreach (['user', 'guest'] as $name) {
        $role = Role::query()->where('name', $name)->where('guard_name', 'web')->firstOrFail();
        expect($role->permissions)->toHaveCount(0);
    }
});

it('is idempotent — running the seeder twice does not duplicate anything', function (): void {
    $permsBefore = Permission::query()->count();
    $rolesBefore = Role::query()->count();

    $this->app->make(ArkheRolesSeeder::class)->run();

    expect(Permission::query()->count())->toBe($permsBefore);
    expect(Role::query()->count())->toBe($rolesBefore);
});

it('does not strip permissions the host app has granted to an Arkhe role', function (): void {
    Permission::query()->firstOrCreate(['name' => 'host-specific', 'guard_name' => 'web']);
    $admin = Role::query()->where('name', 'administrateur')->firstOrFail();
    $admin->givePermissionTo('host-specific');

    $this->app->make(ArkheRolesSeeder::class)->run();

    expect($admin->fresh()->hasPermissionTo('host-specific', 'web'))->toBeTrue();
});

it('lets the backend middleware accept a custom role granted access-backend', function (): void {
    // Probe route: middleware-only, no view rendering — proves the gate
    // accepts a non-canonical role purely on its permission set.
    Route::middleware(['web', 'auth', 'arkhe.backend'])
        ->get('/__probe/backend', fn () => 'ok');

    $custom = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']);
    $custom->givePermissionTo('access-backend');

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'A',
        'last_name'  => 'U',
        'email'      => 'auditor@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $user->assignRole('auditor');

    $this->actingAs($user)
        ->get('/__probe/backend')
        ->assertStatus(200)
        ->assertSee('ok');
});

it('blocks a role that does NOT have access-backend', function (): void {
    Route::middleware(['web', 'auth', 'arkhe.backend'])
        ->get('/__probe/backend', fn () => 'ok');

    $custom = Role::query()->create(['name' => 'lurker', 'guard_name' => 'web']);
    // No permission granted — should hit 403.

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'L',
        'last_name'  => 'U',
        'email'      => 'lurker@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $user->assignRole('lurker');

    $this->actingAs($user)
        ->get('/__probe/backend')
        ->assertForbidden();
});
