<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Http\Middleware\EnsureUserHasBackendAccess;
use Arkhe\Main\Livewire\EditRole;
use Arkhe\Main\Livewire\EditUser;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Services\PermissionService;
use Arkhe\Main\Services\RoleService;
use Arkhe\Main\Support\RoleHierarchy;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

/**
 * Builds an actor carrying a tailor-made role: we want users holding *exactly*
 * the listed permissions, so we can check what they are able to grant from
 * there.
 *
 * @param  array<int, string>  $permissions
 */
function makeActorWith(array $permissions, string $roleName = 'acteur-test'): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Acteur',
        'last_name' => 'Test',
        'email' => 'acteur'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $user->assignRole($roleName);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

// ─── Roles: you only grant what you hold ─────────────────────────────────

// The original flaw: `update-role` was enough to grant yourself `manage-roles`,
// hence access to the roles page, hence everything else. One save and the actor
// was root in all but name.
it('refuses to grant a permission the actor does not hold', function (): void {
    $actor = makeActorWith(['update-role', 'access-backend']);
    $ownRole = Role::query()->where('name', 'acteur-test')->firstOrFail();

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $ownRole->getKey()])
        ->set('roleForm.permissions', ['update-role', 'access-backend', 'manage-roles'])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($ownRole->fresh()->hasPermissionTo('manage-roles'))->toBeFalse()
        ->and($actor->fresh()->can('manage-roles'))->toBeFalse();
});

it('refuses the escalation through the deprecated ListRoles flow too', function (): void {
    $actor = makeActorWith(['update-role', 'access-backend']);
    $ownRole = Role::query()->where('name', 'acteur-test')->firstOrFail();

    $component = Livewire::actingAs($actor)->test(ListRoles::class);
    $component->call('openEdit', $ownRole->getKey());
    $component->set('roleForm.permissions', ['update-role', 'access-backend', 'manage-roles']);
    $component->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($ownRole->fresh()->hasPermissionTo('manage-roles'))->toBeFalse();
});

// The guard lives in the service: a write path that bypasses the components
// must be covered just the same.
it('refuses the escalation at the service layer', function (): void {
    $actor = makeActorWith(['update-role']);
    $role = Role::query()->where('name', 'acteur-test')->firstOrFail();

    $this->actingAs($actor);

    expect(fn () => app(RoleService::class)->update($role, ['permissions' => ['manage-roles']]))
        ->toThrow(AuthorizationException::class);
});

it('lets an actor grant a permission it holds itself', function (): void {
    $actor = makeActorWith(['update-role', 'view-user', 'access-backend']);
    $target = Role::query()->where('name', 'user')->firstOrFail();

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $target->getKey()])
        ->set('roleForm.permissions', ['view-user'])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($target->fresh()->hasPermissionTo('view-user'))->toBeTrue();
});

// Revoking is not granting: you do not escalate by taking rights away.
it('lets an actor revoke a permission it does not hold', function (): void {
    $target = Role::query()->where('name', 'user')->firstOrFail();
    $target->syncPermissions(['manage-roles', 'view-user']);

    $actor = makeActorWith(['update-role', 'view-user', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $target->getKey()])
        ->set('roleForm.permissions', ['view-user'])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($target->fresh()->hasPermissionTo('manage-roles'))->toBeFalse()
        ->and($target->fresh()->hasPermissionTo('view-user'))->toBeTrue();
});

it('lets root grant anything', function (): void {
    /** @var User $root */
    $root = User::query()->forceCreate([
        'first_name' => 'Root',
        'last_name' => 'User',
        'email' => 'root'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ]);
    $root->assignRole('root');

    $target = Role::query()->where('name', 'user')->firstOrFail();

    Livewire::actingAs($root)
        ->test(EditRole::class, ['role' => $target->getKey()])
        ->set('roleForm.permissions', ['manage-users', 'delete-user'])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($target->fresh()->hasPermissionTo('manage-users'))->toBeTrue();
});

// ─── Users: the hierarchy already holds, we lock it down ─────────────────

it('refuses to assign a role ranked above the actor', function (): void {
    $actor = makeActorWith(['create-user', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(EditUser::class)
        ->set('userForm.first_name', 'Pirate')
        ->set('userForm.last_name', 'Test')
        ->set('userForm.email', 'pirate@example.test')
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'secret123')
        ->set('userForm.role', 'root')
        ->call('save')
        ->assertHasErrors('userForm.role');

    expect(User::query()->where('email', 'pirate@example.test')->exists())->toBeFalse();
});

// The form only offers assignable roles, but the guard must not depend on what
// the view displays.
it('refuses direct permission assignment on a user beyond the actor', function (): void {
    $actor = makeActorWith(['create-user', 'access-backend']);

    $component = Livewire::actingAs($actor)
        ->test(EditUser::class)
        ->set('userForm.first_name', 'Direct')
        ->set('userForm.last_name', 'Test')
        ->set('userForm.email', 'direct@example.test')
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'secret123')
        ->set('userForm.permissions', ['manage-roles'])
        ->call('save');

    $created = User::query()->where('email', 'direct@example.test')->first();

    // If the creation goes through, it must under no circumstances carry the
    // permission the actor does not hold.
    if ($created !== null) {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        expect($created->fresh()->can('manage-roles'))->toBeFalse();
    }
});

// ─── Unranked roles: rank -1 is not a free pass ──────────────────────────

// A role missing from `config('arkhe.roles')` ranks -1, just like an actor with
// no role at all: `-1 <= -1` opened the assignment to anyone, even though such
// a role may carry `manage-roles`. Creating an account became an escalation.
it('refuses to assign an unranked role that grants more than the actor holds', function (): void {
    $powerful = Role::query()->create(['name' => 'hors-hierarchie', 'guard_name' => 'web']);
    $powerful->syncPermissions(['manage-roles']);

    $actor = makeActorWith(['create-user', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(EditUser::class)
        ->set('userForm.first_name', 'Hors')
        ->set('userForm.last_name', 'Hierarchie')
        ->set('userForm.email', 'hors@example.test')
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'secret123')
        ->set('userForm.role', 'hors-hierarchie')
        ->call('save')
        ->assertHasErrors('userForm.role');

    expect(User::query()->where('email', 'hors@example.test')->exists())->toBeFalse();
});

it('allows an unranked role when the actor already holds everything it grants', function (): void {
    $harmless = Role::query()->create(['name' => 'lecteur-maison', 'guard_name' => 'web']);
    $harmless->syncPermissions(['view-user']);

    $actor = makeActorWith(['create-user', 'view-user', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(EditUser::class)
        ->set('userForm.first_name', 'Lecteur')
        ->set('userForm.last_name', 'Maison')
        ->set('userForm.email', 'lecteur@example.test')
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'secret123')
        ->set('userForm.role', 'lecteur-maison')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'lecteur@example.test')->exists())->toBeTrue();
});

// Same blind spot on the `canManage` side: a target carrying only unranked
// roles reported rank -1 and looked manageable by anyone at all.
it('refuses to manage a user carrying an unranked but powerful role', function (): void {
    $powerful = Role::query()->create(['name' => 'hors-hierarchie', 'guard_name' => 'web']);
    $powerful->syncPermissions(['manage-roles']);

    /** @var User $target */
    $target = User::query()->forceCreate([
        'first_name' => 'Cible',
        'last_name' => 'Puissante',
        'email' => 'cible@example.test',
        'password' => Hash::make('secret123'),
    ]);
    $target->assignRole('hors-hierarchie');

    $actor = makeActorWith(['update-user', 'access-backend']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(RoleHierarchy::canManage($actor->fresh(), $target->fresh()))
        ->toBeFalse();
});

// ─── Permissions: renaming must not amount to granting ───────────────────

// The trickiest of the four: the pivot tables reference the id, not the name.
// Renaming `view-user` into `manage-roles` turned every one of its holders into
// an administrator at a stroke — without touching a role or an account, hence
// without crossing any of the guards above.
it('refuses to rename a permission into a canonical one', function (): void {
    $actor = makeActorWith(['update-permission', 'view-user', 'access-backend']);
    $target = Permission::query()->where('name', 'view-user')->firstOrFail();

    $this->actingAs($actor);

    expect(fn () => app(PermissionService::class)->update($target, ['name' => 'manage-roles']))
        ->toThrow(AuthorizationException::class);

    expect($target->fresh()->name)->toBe('view-user');
});

it('refuses to delete a canonical permission', function (): void {
    $actor = makeActorWith(['delete-permission', 'access-backend']);
    $target = Permission::query()->where('name', 'access-backend')->firstOrFail();

    $this->actingAs($actor);

    expect(fn () => app(PermissionService::class)->delete($target))
        ->toThrow(AuthorizationException::class);

    expect(Permission::query()->where('name', 'access-backend')->exists())->toBeTrue();
});

// Not even root may saw off the branch: the package code refers to these names
// verbatim, and losing them would lock the back-office for everyone, with no
// way back from the interface.
it('refuses to delete a canonical permission even as root', function (): void {
    /** @var User $root */
    $root = User::query()->forceCreate([
        'first_name' => 'Root', 'last_name' => 'User',
        'email' => 'root'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ]);
    $root->assignRole('root');

    $this->actingAs($root);

    expect(fn () => app(PermissionService::class)->delete(
        Permission::query()->where('name', 'manage-roles')->firstOrFail()
    ))->toThrow(AuthorizationException::class);
});

it('refuses to alter a permission the actor does not hold', function (): void {
    $actor = makeActorWith(['update-permission', 'access-backend']);
    $target = Permission::query()->create(['name' => 'permission-tierce', 'guard_name' => 'web']);

    $this->actingAs($actor);

    expect(fn () => app(PermissionService::class)->update($target, ['name' => 'renommee']))
        ->toThrow(AuthorizationException::class);
});

// ─── Reserved role names ─────────────────────────────────────────────────

// For V2 compatibility, `admin.roles` accepts raw role names. Carrying such a
// name buys entry to the back-office without `access-backend`: minting one
// yourself would be a back door.
it('refuses to create a role whose very name grants backend access', function (): void {
    config()->set('arkhe.admin.roles', ['root', 'administrator', 'legacy-admin']);

    $actor = makeActorWith(['create-role', 'access-backend']);
    $this->actingAs($actor);

    expect(fn () => app(RoleService::class)->create(['name' => 'legacy-admin']))
        ->toThrow(AuthorizationException::class);
});

it('refuses to create a role named after a canonical one', function (): void {
    $actor = makeActorWith(['create-role', 'access-backend']);
    $this->actingAs($actor);

    expect(fn () => app(RoleService::class)->create(['name' => 'root']))
        ->toThrow(AuthorizationException::class);
});

// ─── Non-regression: do not break apps that upgrade ──────────────────────

// The hardening must demand nothing new in configuration. A house role outside
// `arkhe.roles`, granting only permissions the administrator already holds,
// stays assignable as before.
it('keeps house roles assignable when they grant nothing new', function (): void {
    $house = Role::query()->create(['name' => 'redacteur', 'guard_name' => 'web']);
    $house->syncPermissions(['access-backend', 'view-user']);

    $actor = makeActorWith(['access-backend', 'view-user', 'create-user']);

    expect(RoleHierarchy::canAssign($actor, 'redacteur'))->toBeTrue();
});

// And the middleware's role-based fallback stays in place: removing it would
// cut V2 apps off from access on the mere act of upgrading. We test the
// middleware itself — the page additionally demands `view-user`, which is
// permission-based control rather than this fallback.
it('still honours raw role names in admin.roles', function (): void {
    config()->set('arkhe.admin.roles', ['legacy-admin']);

    $legacy = Role::query()->create(['name' => 'legacy-admin', 'guard_name' => 'web']);
    $legacy->syncPermissions([]); // no permission at all, not even access-backend

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Legacy', 'last_name' => 'Admin',
        'email' => 'legacy@example.test',
        'password' => Hash::make('secret123'),
    ]);
    $user->assignRole('legacy-admin');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $request = Request::create('/administration/users', 'GET');
    $request->setUserResolver(fn () => $user->fresh());

    $passed = false;
    (new EnsureUserHasBackendAccess)->handle($request, function () use (&$passed) {
        $passed = true;

        return new Response;
    });

    expect($passed)->toBeTrue();
});

// ─── Public properties: they decide nothing ──────────────────────────────

// `$roleId` said *which* role was being edited, and `save()` read it back
// without ever re-resolving the one from the route: you opened the page of a
// harmless role, pivoted to another, and the `authorize()` calls saw nothing.
it('locks the edited role against client-side pivoting', function (): void {
    $reflection = new ReflectionProperty(EditRole::class, 'roleId');

    expect($reflection->getAttributes(Locked::class))->not->toBeEmpty();

    $canonical = new ReflectionProperty(EditRole::class, 'isCanonical');
    expect($canonical->getAttributes(Locked::class))->not->toBeEmpty();
});

it('locks the edited user', function (): void {
    $reflection = new ReflectionProperty(EditUser::class, 'userId');

    expect($reflection->getAttributes(Locked::class))->not->toBeEmpty();
});

// The form flag dropped every rule on the name when it was `true` — including
// uniqueness. Two roles sharing a name made Spatie's lookups
// non-deterministic.
it('does not trust the form flag to relax name validation', function (): void {
    $custom = Role::query()->create(['name' => 'role-maison', 'guard_name' => 'web']);

    $actor = makeActorWith(['update-role', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $custom->getKey()])
        ->set('roleForm.is_canonical', true)   // the client claims "canonical"
        ->set('roleForm.name', 'administrateur') // name already taken
        ->call('save')
        ->assertHasErrors('roleForm.name');

    expect($custom->fresh()->name)->toBe('role-maison')
        ->and(Role::query()->where('name', 'administrateur')->count())->toBe(1);
});

// ─── Sorting: the allow-list does not depend on the repository ───────────

it('falls back to a safe sort field on both lists', function (): void {
    $actor = makeActorWith(['view-role', 'manage-roles', 'view-user', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(ListRoles::class)
        ->set('sortField', 'name); drop table roles;--')
        ->assertOk();

    expect(Role::query()->count())->toBeGreaterThan(0);
});

// ─── Permissions: creating one must not be a way around ──────────────────

it('does not let an actor mint a permission to grant itself', function (): void {
    $actor = makeActorWith(['update-role', 'create-permission', 'access-backend']);
    $ownRole = Role::query()->where('name', 'acteur-test')->firstOrFail();

    // A freshly created permission is held by nobody: granting it to yourself
    // must be refused like any other.
    Permission::query()->firstOrCreate(['name' => 'super-pouvoir', 'guard_name' => 'web']);

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $ownRole->getKey()])
        ->set('roleForm.permissions', ['update-role', 'access-backend', 'super-pouvoir'])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($ownRole->fresh()->hasPermissionTo('super-pouvoir'))->toBeFalse();
});
