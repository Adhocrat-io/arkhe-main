<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\EditRole;
use Arkhe\Main\Livewire\EditUser;
use Arkhe\Main\Services\UserService;
use Arkhe\Main\Support\PermissionGroups;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

// ─── User page ───────────────────────────────────────────────────────────

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

// No rule keeps the confirmation around: if it falls out of the form's
// serialized contract, Livewire does not hand it back on the next round-trip
// and every creation fails on a pair that actually matched. This test keeps the
// property in the snapshot.
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

// The hierarchy guard does not live in the list alone: reaching the page by its
// URL must not sidestep the rank.
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

// ─── Avatar: deferred removal ────────────────────────────────────────────

// Nothing is deleted before saving: you mark it, you may change your mind, and
// the file only goes away on save.
it('defers the avatar removal until save', function (): void {
    Storage::fake('local');

    $target = makeEditPagesUser('user');
    app(UserService::class)->update($target, ['avatar' => UploadedFile::fake()->image('me.jpg')]);
    $path = $target->fresh()->avatar_path;

    $component = Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class, ['user' => $target->getKey()])
        ->call('markRemoveAvatar')
        ->assertSet('userForm.removeAvatar', true);

    // Marked, but the file is still there.
    Storage::disk('local')->assertExists($path);
    expect($target->fresh()->avatar_path)->toBe($path);

    $component->call('save');

    expect($target->fresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('lets the removal be cancelled before saving', function (): void {
    Storage::fake('local');

    $target = makeEditPagesUser('user');
    app(UserService::class)->update($target, ['avatar' => UploadedFile::fake()->image('me.jpg')]);
    $path = $target->fresh()->avatar_path;

    Livewire::actingAs(makeEditPagesUser('root'))
        ->test(EditUser::class, ['user' => $target->getKey()])
        ->call('markRemoveAvatar')
        ->call('cancelRemoveAvatar')
        ->assertSet('userForm.removeAvatar', false)
        ->call('save');

    expect($target->fresh()->avatar_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
});

// ─── Role page ───────────────────────────────────────────────────────────

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

// A canonical role keeps its name but stays open on the permissions side: that
// is precisely what the page is there to settle.
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

// A role comes from the code, not from the screen: the page edits an existing
// role and has nothing to offer without an id.
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

// ─── Permission grouping ─────────────────────────────────────────────────

it('groups permissions by resource, macros first', function (): void {
    $groups = PermissionGroups::build(['view-user', 'manage-users', 'create-user', 'access-backend']);

    expect($groups)->toHaveKey('users')
        ->and($groups['users'])->toBe(['manage-users', 'create-user', 'view-user'])
        // Whatever names no resource is not lost for all that.
        ->and($groups)->toHaveKey('other')
        ->and($groups['other'])->toBe(['access-backend']);
});

it('honours an explicit permission_groups config', function (): void {
    config()->set('arkhe.permission_groups', [
        'manage-users' => ['view-user', 'create-user'],
    ]);

    $groups = PermissionGroups::build(['view-user', 'create-user', 'manage-users', 'view-role']);

    expect($groups['manage-users'])->toBe(['manage-users', 'view-user', 'create-user'])
        // The config does not mention view-role: it stays visible under "other"
        // rather than vanishing from the screen.
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
