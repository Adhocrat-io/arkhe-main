<?php

declare(strict_types=1);

use Arkhe\Main\Contracts\PermissionRepositoryInterface;
use Arkhe\Main\Contracts\RoleRepositoryInterface;
use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->app->make(ArkheRolesSeeder::class)->run());

it('paginates roles ordered by name', function (): void {
    $page = app(RoleRepositoryInterface::class)->paginate([], 10);

    expect($page->total())->toBe(4);
    expect($page->items()[0]->name)->toBe('administrateur'); // alphabetical
});

it('filters roles by name search', function (): void {
    $page = app(RoleRepositoryInterface::class)->paginate(['search' => 'root']);

    expect($page->total())->toBe(1);
    expect($page->items()[0]->name)->toBe('root');
});

it('finds a role by id and by name', function (): void {
    $repo = app(RoleRepositoryInterface::class);
    $root = Role::query()->where('name', 'root')->first();

    expect($repo->find($root->id)->name)->toBe('root');
    expect($repo->findByName('user')->name)->toBe('user');
    expect($repo->findByName('does-not-exist'))->toBeNull();
});

it('eager-loads permissions on the role', function (): void {
    Permission::query()->create(['name' => 'foo', 'guard_name' => 'web']);
    $root = Role::query()->where('name', 'root')->first();
    $root->givePermissionTo('foo');

    $loaded = app(RoleRepositoryInterface::class)->find($root->id);

    expect($loaded->relationLoaded('permissions'))->toBeTrue();
    expect($loaded->permissions->pluck('name')->all())->toContain('foo');
});

it('exposes all permissions ordered alphabetically', function (): void {
    // Wipe the seeder's permissions so this test asserts on a known fixture.
    Permission::query()->delete();

    Permission::query()->create(['name' => 'zeta',  'guard_name' => 'web']);
    Permission::query()->create(['name' => 'alpha', 'guard_name' => 'web']);

    $names = app(PermissionRepositoryInterface::class)->all()->pluck('name')->all();

    expect($names)->toBe(['alpha', 'zeta']);
});

it('paginates permissions and filters by search', function (): void {
    Permission::query()->delete();

    Permission::query()->create(['name' => 'edit-foo', 'guard_name' => 'web']);
    Permission::query()->create(['name' => 'edit-bar', 'guard_name' => 'web']);
    Permission::query()->create(['name' => 'view-x',   'guard_name' => 'web']);

    $repo = app(PermissionRepositoryInterface::class);

    expect($repo->paginate(['search' => 'edit-'])->total())->toBe(2);
    expect($repo->paginate(['search' => 'view-'])->total())->toBe(1);
});
