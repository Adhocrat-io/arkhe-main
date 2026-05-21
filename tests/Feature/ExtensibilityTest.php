<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Covers the two extensibility levers added on top of arkhe v0.1:
 * - components can be swapped via config('arkhe.components')
 * - lifecycle hooks (beforeSave/afterCreate/afterUpdate/beforeDelete) are
 *   called by the default components and can be overridden by a subclass.
 *
 * Note: hook coverage focuses on the UPDATE and DELETE paths. The CREATE
 * path relies on the UserForm's password confirmation, which Livewire 4's
 * testing harness does not rehydrate reliably between ->set() calls
 * (see the same caveat in ListUsersTest). The hooks themselves don't care
 * which branch was taken — they're invoked by the same `save()` body.
 */

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();

    /** @var User $admin */
    $admin = User::query()->forceCreate([
        'first_name' => 'Ext',
        'last_name'  => 'Tester',
        'email'      => 'ext-tester@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $admin->assignRole('root');

    Livewire::actingAs($admin);
});

function rebootArkhe(): void
{
    $app = app();
    $app->make(\Arkhe\Main\ArkheMainServiceProvider::class, ['app' => $app])
        ->packageBooted();
}

it('resolves a Livewire component alias to a host-app subclass declared via config', function (): void {
    config(['arkhe.components.list-users' => TestExtensibilityListUsers::class]);
    rebootArkhe();

    $component = Livewire::test('arkhe.list-users');

    expect(get_class($component->instance()))->toBe(TestExtensibilityListUsers::class);
});

it('falls back to the package default when no override is configured', function (): void {
    $component = Livewire::test('arkhe.list-users');

    expect(get_class($component->instance()))->toBe(ListUsers::class);
});

it('calls beforeSave + afterUpdate in order when editing an existing user', function (): void {
    config(['arkhe.components.list-users' => TestExtensibilityListUsers::class]);
    rebootArkhe();

    /** @var User $target */
    $target = User::query()->forceCreate([
        'first_name' => 'Edit',
        'last_name'  => 'Me',
        'email'      => 'edit-me@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $target->assignRole('user');

    TestExtensibilityListUsers::$calls = [];

    Livewire::test('arkhe.list-users')
        ->call('openEdit', $target->id)
        ->set('userForm.first_name', 'Edited')
        ->call('save');

    expect(TestExtensibilityListUsers::$calls)->toBe(['beforeSave', 'afterUpdate']);
    expect($target->fresh()->first_name)->toBe('Edited');
});

it('lets beforeSave mutate the payload before it reaches the service', function (): void {
    config(['arkhe.components.list-users' => MutatingListUsers::class]);
    rebootArkhe();

    /** @var User $target */
    $target = User::query()->forceCreate([
        'first_name' => 'Original',
        'last_name'  => 'Name',
        'email'      => 'mutate@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $target->assignRole('user');

    Livewire::test('arkhe.list-users')
        ->call('openEdit', $target->id)
        ->set('userForm.first_name', 'WillBeOverridden')
        ->call('save');

    expect($target->fresh()->first_name)->toBe('OVERRIDDEN');
});

it('calls beforeDelete just before the service deletes the user', function (): void {
    config(['arkhe.components.list-users' => TestExtensibilityListUsers::class]);
    rebootArkhe();

    /** @var User $target */
    $target = User::query()->forceCreate([
        'first_name' => 'Del',
        'last_name'  => 'Me',
        'email'      => 'del-me@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $target->assignRole('user');

    TestExtensibilityListUsers::$calls = [];

    Livewire::test('arkhe.list-users')
        ->call('confirmDelete', $target->id)
        ->call('delete');

    expect(TestExtensibilityListUsers::$calls)->toBe(['beforeDelete']);
    expect(User::query()->find($target->id))->toBeNull();
});

it('exposes the same hooks on ListRoles and ListPermissions', function (): void {
    // Smoke check: the methods exist with the documented signatures so a
    // subclass can declare them without surprise.
    $rolesHooks = new ReflectionClass(\Arkhe\Main\Livewire\ListRoles::class);
    $permsHooks = new ReflectionClass(\Arkhe\Main\Livewire\ListPermissions::class);

    foreach (['beforeSave', 'afterCreate', 'afterUpdate', 'beforeDelete'] as $hook) {
        expect($rolesHooks->hasMethod($hook))->toBeTrue("ListRoles missing {$hook}");
        expect($permsHooks->hasMethod($hook))->toBeTrue("ListPermissions missing {$hook}");

        expect($rolesHooks->getMethod($hook)->isProtected())->toBeTrue();
        expect($permsHooks->getMethod($hook)->isProtected())->toBeTrue();
    }
});

/**
 * Test double for the override + hook tracing assertions.
 */
class TestExtensibilityListUsers extends ListUsers
{
    /** @var array<int, string> */
    public static array $calls = [];

    protected function beforeSave(array $payload): array
    {
        self::$calls[] = 'beforeSave';

        return $payload;
    }

    protected function afterCreate(Model $user, array $payload): void
    {
        self::$calls[] = 'afterCreate';
    }

    protected function afterUpdate(Model $user, array $payload): void
    {
        self::$calls[] = 'afterUpdate';
    }

    protected function beforeDelete(Model $user): void
    {
        self::$calls[] = 'beforeDelete';
    }
}

class MutatingListUsers extends ListUsers
{
    protected function beforeSave(array $payload): array
    {
        $payload['first_name'] = 'OVERRIDDEN';

        return $payload;
    }
}
