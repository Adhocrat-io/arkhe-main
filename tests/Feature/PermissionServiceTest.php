<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Events\PermissionCreated;
use Arkhe\Main\Events\PermissionDeleted;
use Arkhe\Main\Events\PermissionUpdated;
use Arkhe\Main\Services\PermissionService;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->app->make(ArkheRolesSeeder::class)->run());

it('creates a permission with the default web guard', function (): void {
    Event::fake([PermissionCreated::class]);

    $p = app(PermissionService::class)->create(['name' => 'edit-posts']);

    expect($p->name)->toBe('edit-posts');
    expect($p->guard_name)->toBe('web');
    Event::assertDispatched(PermissionCreated::class);
});

it('renames a permission on update', function (): void {
    Event::fake([PermissionUpdated::class]);
    $p = app(PermissionService::class)->create(['name' => 'old']);

    app(PermissionService::class)->update($p, ['name' => 'new']);

    expect($p->fresh()->name)->toBe('new');
    Event::assertDispatched(PermissionUpdated::class);
});

it('deletes a permission and cascades on role pivots', function (): void {
    Event::fake([PermissionDeleted::class]);
    $p     = app(PermissionService::class)->create(['name' => 'editable']);
    $role  = Role::query()->where('name', 'root')->first();
    $role->givePermissionTo($p);

    expect($role->fresh()->permissions->pluck('name')->all())->toContain('editable');

    app(PermissionService::class)->delete($p);

    expect(Permission::query()->where('name', 'editable')->first())->toBeNull();
    expect($role->fresh()->permissions->pluck('name')->all())->not->toContain('editable');
    Event::assertDispatched(PermissionDeleted::class);
});
