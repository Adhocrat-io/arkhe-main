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
 * Fabrique un acteur porteur d'un rôle sur mesure : on veut des utilisateurs
 * qui détiennent *exactement* les permissions listées, pour vérifier ce qu'ils
 * peuvent accorder à partir de là.
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

// ─── Rôles : on n'accorde que ce qu'on détient ───────────────────────────

// La faille d'origine : `update-role` suffisait à s'attribuer `manage-roles`,
// donc l'accès à la page des rôles, donc tout le reste. Un enregistrement et
// l'acteur devenait root de fait.
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

// La garde vit dans le service : un chemin d'écriture qui ne passe pas par
// les composants doit être couvert de la même façon.
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

// Retirer n'est pas accorder : on ne s'élève pas en enlevant des droits.
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

// ─── Utilisateurs : la hiérarchie tient déjà, on la verrouille ───────────

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

// Le formulaire ne propose que les rôles assignables, mais la garde ne doit
// pas dépendre de ce que la vue affiche.
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

    // Si la création aboutit, elle ne doit en aucun cas embarquer la
    // permission que l'acteur ne détient pas.
    if ($created !== null) {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        expect($created->fresh()->can('manage-roles'))->toBeFalse();
    }
});

// ─── Rôles hors hiérarchie : le rang -1 n'est pas un laissez-passer ──────

// Un rôle absent de `config('arkhe.roles')` a le rang -1, comme un acteur qui
// n'a aucun rôle : `-1 <= -1` ouvrait l'attribution à n'importe qui, alors que
// ce rôle peut porter `manage-roles`. Créer un compte devenait une escalade.
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

// Même angle mort côté `canManage` : une cible qui ne porte que des rôles hors
// hiérarchie affichait le rang -1 et paraissait gérable par le premier venu.
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

// ─── Permissions : renommer ne doit pas valoir s'accorder ────────────────

// La plus retorse des quatre : les tables pivots référencent l'identifiant,
// pas le nom. Rebaptiser `view-user` en `manage-roles` transformait d'un coup
// tous ses porteurs en administrateurs — sans toucher à un rôle ni à un
// compte, donc sans croiser aucune des gardes précédentes.
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

// Même root ne doit pas pouvoir couper la branche : le code du paquet se
// réfère à ces noms en dur, les perdre verrouillerait le back-office pour
// tout le monde, sans retour possible depuis l'interface.
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

// ─── Noms de rôles réservés ──────────────────────────────────────────────

// `admin.roles` accepte, en compatibilité V2, des noms de rôles bruts. Porter
// un tel nom vaut l'entrée dans le back-office sans `access-backend` : le
// fabriquer soi-même serait une porte dérobée.
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

// ─── Non-régression : ne pas casser les apps qui montent de version ──────

// Le durcissement ne doit rien exiger de neuf en configuration. Un rôle maison
// hors `arkhe.roles`, qui n'accorde que des permissions déjà détenues par
// l'administrateur, reste attribuable comme avant.
it('keeps house roles assignable when they grant nothing new', function (): void {
    $house = Role::query()->create(['name' => 'redacteur', 'guard_name' => 'web']);
    $house->syncPermissions(['access-backend', 'view-user']);

    $actor = makeActorWith(['access-backend', 'view-user', 'create-user']);

    expect(RoleHierarchy::canAssign($actor, 'redacteur'))->toBeTrue();
});

// Et le repli par rôle du middleware reste en place : le retirer priverait
// d'accès les apps V2 à la simple montée de version. On teste le middleware
// lui-même — la page, elle, demande en plus `view-user`, ce qui relève du
// contrôle par permission et non de ce repli.
it('still honours raw role names in admin.roles', function (): void {
    config()->set('arkhe.admin.roles', ['legacy-admin']);

    $legacy = Role::query()->create(['name' => 'legacy-admin', 'guard_name' => 'web']);
    $legacy->syncPermissions([]); // aucune permission, pas même access-backend

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

// ─── Propriétés publiques : elles ne décident de rien ────────────────────

// `$roleId` disait *quel* rôle on édite, et `save()` la relisait sans jamais
// re-résoudre celui de la route : on ouvrait la fiche d'un rôle anodin, on
// pivotait vers un autre, et les `authorize()` n'y voyaient rien.
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

// Le drapeau du formulaire faisait tomber toutes les règles du nom quand il
// valait `true` — y compris l'unicité. Deux rôles homonymes rendaient les
// recherches de Spatie non déterministes.
it('does not trust the form flag to relax name validation', function (): void {
    $custom = Role::query()->create(['name' => 'role-maison', 'guard_name' => 'web']);

    $actor = makeActorWith(['update-role', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $custom->getKey()])
        ->set('roleForm.is_canonical', true)   // le client prétend « canonique »
        ->set('roleForm.name', 'administrateur') // nom déjà pris
        ->call('save')
        ->assertHasErrors('roleForm.name');

    expect($custom->fresh()->name)->toBe('role-maison')
        ->and(Role::query()->where('name', 'administrateur')->count())->toBe(1);
});

// ─── Tri : la liste blanche ne dépend pas du repository ──────────────────

it('falls back to a safe sort field on both lists', function (): void {
    $actor = makeActorWith(['view-role', 'manage-roles', 'view-user', 'access-backend']);

    Livewire::actingAs($actor)
        ->test(ListRoles::class)
        ->set('sortField', 'name); drop table roles;--')
        ->assertOk();

    expect(Role::query()->count())->toBeGreaterThan(0);
});

// ─── Permissions : la création ne doit pas être un contournement ─────────

it('does not let an actor mint a permission to grant itself', function (): void {
    $actor = makeActorWith(['update-role', 'create-permission', 'access-backend']);
    $ownRole = Role::query()->where('name', 'acteur-test')->firstOrFail();

    // Une permission fraîchement créée n'est détenue par personne : se
    // l'attribuer doit être refusé comme les autres.
    Permission::query()->firstOrCreate(['name' => 'super-pouvoir', 'guard_name' => 'web']);

    Livewire::actingAs($actor)
        ->test(EditRole::class, ['role' => $ownRole->getKey()])
        ->set('roleForm.permissions', ['update-role', 'access-backend', 'super-pouvoir'])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($ownRole->fresh()->hasPermissionTo('super-pouvoir'))->toBeFalse();
});
