<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Support\RoleHierarchy;
use Arkhe\Main\Tests\Stubs\User;

beforeEach(function (): void {
    RoleHierarchy::reset();
    $this->app->make(ArkheRolesSeeder::class)->run();
});

afterEach(fn () => RoleHierarchy::reset());

function makeRoleHierarchyUser(string $role): User
{
    /** @var User $u */
    $u = User::query()->forceCreate([
        'email'    => 'rh-'.uniqid().'@x.test',
        'password' => 'x',
    ]);
    $u->assignRole($role);

    return $u;
}

it('builds ranks from the config order, highest first', function (): void {
    $ranks = RoleHierarchy::ranks();

    expect($ranks['root'])->toBe(3);
    expect($ranks['administrateur'])->toBe(2);
    expect($ranks['user'])->toBe(1);
    expect($ranks['guest'])->toBe(0);
});

it('returns -1 for an unknown role', function (): void {
    expect(RoleHierarchy::rankOf('unknown-role'))->toBe(-1);
    expect(RoleHierarchy::rankOf(null))->toBe(-1);
    expect(RoleHierarchy::rankOf(''))->toBe(-1);
});

it('computes the highest rank of a user from their assigned roles', function (): void {
    $root  = makeRoleHierarchyUser('root');
    $admin = makeRoleHierarchyUser('administrateur');
    $user  = makeRoleHierarchyUser('user');

    expect(RoleHierarchy::highestRankOf($root))->toBe(3);
    expect(RoleHierarchy::highestRankOf($admin))->toBe(2);
    expect(RoleHierarchy::highestRankOf($user))->toBe(1);
    expect(RoleHierarchy::highestRankOf(null))->toBe(-1);
});

it('allows an actor to assign a role at or below their rank', function (): void {
    $root  = makeRoleHierarchyUser('root');
    $admin = makeRoleHierarchyUser('administrateur');

    expect(RoleHierarchy::canAssign($root, 'root'))->toBeTrue();
    expect(RoleHierarchy::canAssign($root, 'administrateur'))->toBeTrue();
    expect(RoleHierarchy::canAssign($root, 'user'))->toBeTrue();

    expect(RoleHierarchy::canAssign($admin, 'administrateur'))->toBeTrue();
    expect(RoleHierarchy::canAssign($admin, 'user'))->toBeTrue();
});

it('rejects an attempt to assign a role above the actor', function (): void {
    $admin = makeRoleHierarchyUser('administrateur');
    $user  = makeRoleHierarchyUser('user');

    expect(RoleHierarchy::canAssign($admin, 'root'))->toBeFalse();
    expect(RoleHierarchy::canAssign($user,  'root'))->toBeFalse();
    expect(RoleHierarchy::canAssign($user,  'administrateur'))->toBeFalse();
});

it('treats clearing the role (null/empty) as always allowed', function (): void {
    expect(RoleHierarchy::canAssign(null, null))->toBeTrue();
    expect(RoleHierarchy::canAssign(null, ''))->toBeTrue();
});

it('allows managing a target user at or below the actor rank', function (): void {
    $root  = makeRoleHierarchyUser('root');
    $admin = makeRoleHierarchyUser('administrateur');
    $user  = makeRoleHierarchyUser('user');

    expect(RoleHierarchy::canManage($root, $admin))->toBeTrue();
    expect(RoleHierarchy::canManage($root, $user))->toBeTrue();
    expect(RoleHierarchy::canManage($admin, $user))->toBeTrue();
});

it('rejects managing a target user above the actor rank', function (): void {
    $admin = makeRoleHierarchyUser('administrateur');
    $root  = makeRoleHierarchyUser('root');

    expect(RoleHierarchy::canManage($admin, $root))->toBeFalse();
    expect(RoleHierarchy::canManage(null, $root))->toBeFalse();
});

it('lists the roles assignable by a given actor', function (): void {
    $root  = makeRoleHierarchyUser('root');
    $admin = makeRoleHierarchyUser('administrateur');
    $user  = makeRoleHierarchyUser('user');

    expect(RoleHierarchy::rolesAssignableBy($root))
        ->toBe(['root', 'administrateur', 'user', 'guest']);

    expect(RoleHierarchy::rolesAssignableBy($admin))
        ->toBe(['administrateur', 'user', 'guest']);

    expect(RoleHierarchy::rolesAssignableBy($user))
        ->toBe(['user', 'guest']);
});

it('registers a new role after an existing one at runtime', function (): void {
    RoleHierarchy::register('manager', after: 'administrateur');

    $ranks = RoleHierarchy::ranks();
    expect(array_keys($ranks))->toBe(['root', 'administrateur', 'manager', 'user', 'guest']);

    // Ranks shift down: original `user` was 1, now manager occupies that slot.
    expect($ranks['root'])->toBe(4);
    expect($ranks['administrateur'])->toBe(3);
    expect($ranks['manager'])->toBe(2);
    expect($ranks['user'])->toBe(1);
});

it('registers a new role before an existing one at runtime', function (): void {
    RoleHierarchy::register('editor', before: 'user');

    expect(array_keys(RoleHierarchy::ranks()))
        ->toBe(['root', 'administrateur', 'editor', 'user', 'guest']);
});

it('appends a new role at the lowest rank when no anchor is provided', function (): void {
    RoleHierarchy::register('intern');

    expect(array_keys(RoleHierarchy::ranks()))
        ->toBe(['root', 'administrateur', 'user', 'guest', 'intern']);
});

it('repositions an already-registered role on a subsequent register call', function (): void {
    RoleHierarchy::register('manager', after: 'administrateur');
    RoleHierarchy::register('manager', before: 'root');

    expect(array_keys(RoleHierarchy::ranks()))
        ->toBe(['manager', 'root', 'administrateur', 'user', 'guest']);
});

it('refuses both `after` and `before` together', function (): void {
    expect(fn () => RoleHierarchy::register('x', after: 'root', before: 'user'))
        ->toThrow(InvalidArgumentException::class);
});

it('falls back to config order after reset', function (): void {
    RoleHierarchy::register('manager', after: 'administrateur');
    expect(count(RoleHierarchy::ranks()))->toBe(5);

    RoleHierarchy::reset();
    expect(array_keys(RoleHierarchy::ranks()))
        ->toBe(['root', 'administrateur', 'user', 'guest']);
});
