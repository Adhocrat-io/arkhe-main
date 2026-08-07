<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\EditRole;
use Arkhe\Main\Livewire\EditUser;
use Arkhe\Main\Support\PermissionGroups;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

function makeEditPagesUser(?string $role = null, array $attrs = []): User
{
    /** @var User $user */
    $user = User::query()->forceCreate(array_merge([
        'first_name' => 'Page',
        'last_name' => 'Tester',
        'email' => 'page'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ], $attrs));

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

// ─── Fiche utilisateur ───────────────────────────────────────────────────

it('creates a user from the dedicated page', function (): void {
    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class)
        ->set('userForm.first_name', 'Nouvelle')
        ->set('userForm.last_name', 'Recrue')
        ->set('userForm.email', 'recrue@example.test')
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'secret123')
        ->set('userForm.role', 'user')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('arkhe.users.index'));

    $created = User::query()->where('email', 'recrue@example.test')->first();

    expect($created)->not->toBeNull()
        ->and($created->getRoleNames()->all())->toBe(['user']);
});

// La confirmation ne fait partie d'aucune règle qui la conserve : si elle
// sort du contrat sérialisé du formulaire, Livewire ne la restitue pas au
// prochain aller-retour et toute création échoue sur une paire pourtant
// identique. Ce test garde la propriété dans le snapshot.
it('keeps the password confirmation across a Livewire round-trip', function (): void {
    $component = Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class)
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'secret123');

    expect($component->snapshot['data']['userForm'][0])
        ->toHaveKey('passwordConfirmation');
});

it('rejects a mismatched password confirmation', function (): void {
    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class)
        ->set('userForm.first_name', 'Paire')
        ->set('userForm.last_name', 'Fausse')
        ->set('userForm.email', 'paire@example.test')
        ->set('userForm.password', 'secret123')
        ->set('userForm.passwordConfirmation', 'autre-chose')
        ->call('save')
        ->assertHasErrors('userForm.password');

    expect(User::query()->where('email', 'paire@example.test')->exists())->toBeFalse();
});

it('loads an existing user into the form when editing', function (): void {
    $target = makeEditPagesUser('user', ['first_name' => 'Camille', 'email' => 'camille@example.test']);

    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class, ['user' => $target->getKey()])
        ->assertSet('userForm.first_name', 'Camille')
        ->assertSet('userForm.email', 'camille@example.test')
        ->assertSet('userForm.role', 'user');
});

it('updates a user from the dedicated page', function (): void {
    $target = makeEditPagesUser('user', ['first_name' => 'Avant']);

    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class, ['user' => $target->getKey()])
        ->set('userForm.first_name', 'Après')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('arkhe.users.index'));

    expect($target->refresh()->first_name)->toBe('Après');
});

// Le garde-fou de hiérarchie ne vit pas que dans la liste : arriver sur la
// fiche par son URL ne doit pas contourner le rang.
it('refuses to open the page of a user who outranks the actor', function (): void {
    $root = makeEditPagesUser('root');
    $admin = makeEditPagesUser('administrateur');

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['user' => $root->getKey()])
        ->assertForbidden();
});

it('404s on an unknown user', function (): void {
    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class, ['user' => 99999])
        ->assertNotFound();
});

// ─── Fiche rôle ──────────────────────────────────────────────────────────

it('attaches permissions to a role from the dedicated page', function (): void {
    $role = Role::query()->where('name', 'user')->firstOrFail();

    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditRole::class, ['role' => $role->getKey()])
        ->set('roleForm.permissions', ['view-user', 'update-user'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('arkhe.roles.index'));

    expect($role->refresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['update-user', 'view-user']);
});

// Un rôle canonique garde son nom, mais reste ouvert côté permissions :
// c'est précisément ce que la page sert à régler.
it('keeps a canonical role editable on its permissions', function (): void {
    $role = Role::query()->where('name', 'guest')->firstOrFail();

    $component = Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditRole::class, ['role' => $role->getKey()])
        ->assertSet('isCanonical', true)
        ->set('roleForm.permissions', ['view-user'])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->refresh()->permissions->pluck('name')->all())->toBe(['view-user'])
        ->and($role->name)->toBe('guest');
});

it('toggles a whole permission group at once', function (): void {
    $role = Role::query()->where('name', 'user')->firstOrFail();

    $component = Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditRole::class, ['role' => $role->getKey()])
        ->call('toggleGroup', ['view-user', 'create-user'], true);

    expect($component->get('roleForm.permissions'))
        ->toContain('view-user')
        ->toContain('create-user');

    $component->call('toggleGroup', ['view-user'], false);

    expect($component->get('roleForm.permissions'))
        ->not->toContain('view-user')
        ->toContain('create-user');
});

// Un rôle vient du code, pas de l'écran : la fiche édite un rôle existant et
// n'a rien à offrir sans identifiant.
it('404s when the role page is mounted without an id', function (): void {
    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditRole::class)
        ->assertNotFound();
});

it('404s on an unknown role', function (): void {
    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditRole::class, ['role' => 99999])
        ->assertNotFound();
});

// ─── Groupement des permissions ──────────────────────────────────────────

it('groups permissions by resource, macros first', function (): void {
    $groups = PermissionGroups::build(['view-user', 'manage-users', 'create-user', 'access-backend']);

    expect($groups)->toHaveKey('users')
        ->and($groups['users'])->toBe(['manage-users', 'create-user', 'view-user'])
        // Ce qui ne nomme aucune ressource n'est pas perdu pour autant.
        ->and($groups)->toHaveKey('other')
        ->and($groups['other'])->toBe(['access-backend']);
});

it('honours an explicit permission_groups config', function (): void {
    config()->set('arkhe.permission_groups', [
        'manage-users' => ['view-user', 'create-user'],
    ]);

    $groups = PermissionGroups::build(['view-user', 'create-user', 'manage-users', 'view-role']);

    expect($groups['manage-users'])->toBe(['manage-users', 'view-user', 'create-user'])
        // La config ne mentionne pas view-role : il reste visible en « autres »
        // plutôt que de disparaître de l'écran.
        ->and($groups['other'])->toBe(['view-role']);
});

it('drops configured permissions that do not exist', function (): void {
    config()->set('arkhe.permission_groups', [
        'manage-users' => ['view-user', 'permission-fantome'],
    ]);

    $groups = PermissionGroups::build(['view-user']);

    expect($groups['manage-users'])->toBe(['view-user']);
});

it('renders every seeded permission exactly once', function (): void {
    $names = Permission::query()->pluck('name');
    $groups = PermissionGroups::build($names);

    $flattened = collect($groups)->flatten();

    expect($flattened->count())->toBe($names->count())
        ->and($flattened->unique()->count())->toBe($names->count());
});
