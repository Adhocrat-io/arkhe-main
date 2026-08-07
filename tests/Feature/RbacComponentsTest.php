<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\ListPermissions;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

function makeRbacUser(?string $role = null): User
{
    /** @var User $u */
    $u = User::query()->forceCreate([
        'first_name' => 'X',
        'last_name'  => 'Y',
        'email'      => 'rbac-'.uniqid().'@x.test',
        'password'   => Hash::make('x'),
    ]);

    if ($role !== null) {
        $u->assignRole($role);
    }

    return $u;
}

// ─── ListRoles ────────────────────────────────────────────────────────────────

it('lets a root view the ListRoles component', function (): void {
    $root = makeRbacUser('root');

    Livewire::actingAs($root)
        ->test(ListRoles::class)
        ->assertStatus(200)
        ->assertSee('root');
});

it('blocks administrateur from /administration/roles', function (): void {
    $admin = makeRbacUser('administrateur');

    $this->actingAs($admin)
        ->get('/administration/roles')
        ->assertForbidden();
});

it('searches roles by name', function (): void {
    $root = makeRbacUser('root');

    Livewire::actingAs($root)
        ->test(ListRoles::class)
        ->set('search', 'user')
        ->assertSee('user')
        ->assertDontSee('root', false);
})->skip('view rendering also outputs the actor `root` role badge elsewhere; covered by repository search test');

// The next three tests exercise methods deprecated in 3.3: none is reachable
// from the interface any more, but UPGRADE.md promises apps they stay callable
// until the next major. So they check that the promise holds, not a user
// journey.
it('creates a new role through the ListRoles flow', function (): void {
    $root = makeRbacUser('root');

    Livewire::actingAs($root)
        ->test(ListRoles::class)
        ->call('openCreate')
        ->set('roleForm.name', 'editor')
        ->set('roleForm.guard_name', 'web')
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'editor')->first())->not->toBeNull();
});

it('refuses to delete a canonical role through the component', function (): void {
    $root = makeRbacUser('root');
    $rootRole = Role::query()->where('name', 'root')->first();

    Livewire::actingAs($root)
        ->test(ListRoles::class)
        ->call('confirmDelete', $rootRole->id)
        ->assertSet('showDeleteModal', false);

    expect(Role::query()->find($rootRole->id))->not->toBeNull();
});

it('deletes a non-canonical role through the component', function (): void {
    $root = makeRbacUser('root');
    $custom = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);

    Livewire::actingAs($root)
        ->test(ListRoles::class)
        ->call('confirmDelete', $custom->id)
        ->call('delete')
        ->assertSet('showDeleteModal', false);

    expect(Role::query()->find($custom->id))->toBeNull();
});

// ─── ListPermissions ──────────────────────────────────────────────────────────

it('lets a root view the ListPermissions component', function (): void {
    $root = makeRbacUser('root');

    Livewire::actingAs($root)
        ->test(ListPermissions::class)
        ->assertStatus(200);
});

it('blocks administrateur from /administration/permissions', function (): void {
    $admin = makeRbacUser('administrateur');

    $this->actingAs($admin)
        ->get('/administration/permissions')
        ->assertForbidden();
});

// Permissions are managed from the roles page: the old URL survives as a
// redirect, so links in consuming apps do not break.
it('redirects the legacy permissions page to the roles page', function (): void {
    $root = makeRbacUser('root');

    $this->actingAs($root)
        ->get('/administration/permissions')
        ->assertRedirect('/administration/roles');
});

it('keeps the arkhe.permissions.index route name resolvable', function (): void {
    expect(route('arkhe.permissions.index', absolute: false))
        ->toBe('/administration/permissions');
});

// Its counterpart: this one goes away. Roles come from `config('arkhe.roles')`
// and the seeder, the interface no longer mints any.
it('no longer registers a role creation route', function (): void {
    expect(Route::has('arkhe.roles.create'))->toBeFalse();
});

it('offers neither creation nor deletion on the roles list', function (): void {
    // A non-canonical role: without it, the absence of the "Delete" button
    // would prove nothing, every seeded role being protected anyway.
    Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);

    Livewire::actingAs(makeRbacUser('root'))
        ->test(ListRoles::class)
        ->assertDontSee('roles/create')
        ->assertDontSee('confirmDelete');
});

it('creates a permission through the ListPermissions flow', function (): void {
    $root = makeRbacUser('root');

    Livewire::actingAs($root)
        ->test(ListPermissions::class)
        ->call('openCreate')
        ->set('permissionForm.name', 'edit-foo')
        ->set('permissionForm.guard_name', 'web')
        ->call('save')
        ->assertHasNoErrors();

    expect(Permission::query()->where('name', 'edit-foo')->first())->not->toBeNull();
});

it('deletes a permission through the ListPermissions flow', function (): void {
    $root = makeRbacUser('root');
    $p    = Permission::query()->create(['name' => 'temp', 'guard_name' => 'web']);

    Livewire::actingAs($root)
        ->test(ListPermissions::class)
        ->call('confirmDelete', $p->id)
        ->call('delete');

    expect(Permission::query()->find($p->id))->toBeNull();
});

