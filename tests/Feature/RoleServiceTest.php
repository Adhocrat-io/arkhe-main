<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Arkhe\Main\Services\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->app->make(ArkheRolesSeeder::class)->run());

it('creates a role with default web guard', function (): void {
    Event::fake([RoleCreated::class]);

    $role = app(RoleService::class)->create(['name' => 'editor']);

    expect($role->name)->toBe('editor');
    expect($role->guard_name)->toBe('web');
    Event::assertDispatched(RoleCreated::class);
});

it('creates a role with attached permissions', function (): void {
    Permission::query()->create(['name' => 'edit-posts', 'guard_name' => 'web']);
    Permission::query()->create(['name' => 'delete-posts', 'guard_name' => 'web']);

    $role = app(RoleService::class)->create([
        'name'        => 'editor',
        'permissions' => ['edit-posts', 'delete-posts'],
    ]);

    expect($role->permissions->pluck('name')->all())
        ->toContain('edit-posts', 'delete-posts');
});

it('renames a non-canonical role on update', function (): void {
    Event::fake([RoleUpdated::class]);
    $role = app(RoleService::class)->create(['name' => 'editor']);

    app(RoleService::class)->update($role, ['name' => 'redactor']);

    expect($role->fresh()->name)->toBe('redactor');
    Event::assertDispatched(RoleUpdated::class);
});

it('keeps a canonical role name immutable on update', function (): void {
    $root = Role::query()->where('name', 'root')->first();

    app(RoleService::class)->update($root, ['name' => 'i-want-this']);

    expect($root->fresh()->name)->toBe('root');
});

it('allows attaching permissions to a canonical role', function (): void {
    Permission::query()->create(['name' => 'view-dashboard', 'guard_name' => 'web']);
    $root = Role::query()->where('name', 'root')->first();

    app(RoleService::class)->update($root, [
        'permissions' => ['view-dashboard'],
    ]);

    expect($root->fresh()->permissions->pluck('name')->all())
        ->toContain('view-dashboard');
});

it('deletes a non-canonical role and dispatches RoleDeleted', function (): void {
    Event::fake([RoleDeleted::class]);
    $role = app(RoleService::class)->create(['name' => 'temp']);
    $id   = $role->id;

    app(RoleService::class)->delete($role);

    expect(Role::query()->find($id))->toBeNull();
    Event::assertDispatched(RoleDeleted::class);
});

it('refuses to delete any canonical role', function (): void {
    $root = Role::query()->where('name', 'root')->first();

    expect(fn () => app(RoleService::class)->delete($root))
        ->toThrow(AuthorizationException::class);

    expect(Role::query()->find($root->id))->not->toBeNull();
});

it('reports correctly whether a role is canonical', function (): void {
    $svc = app(RoleService::class);

    expect($svc->isCanonical('root'))->toBeTrue();
    expect($svc->isCanonical('administrateur'))->toBeTrue();
    expect($svc->isCanonical('user'))->toBeTrue();
    expect($svc->isCanonical('guest'))->toBeTrue();
    expect($svc->isCanonical('editor'))->toBeFalse();
});
