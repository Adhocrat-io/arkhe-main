<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Events\UserCreated;
use Arkhe\Main\Events\UserDeleted;
use Arkhe\Main\Events\UserUpdated;
use Arkhe\Main\Services\UserService;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
    Storage::fake('local');
});

function makeServiceUser(?string $role = null): User
{
    /** @var User $u */
    $u = User::query()->forceCreate([
        'first_name' => 'X',
        'last_name' => 'Y',
        'email' => 'svc-'.uniqid().'@x.test',
        'password' => Hash::make('x'),
    ]);

    if ($role !== null) {
        $u->assignRole($role);
    }

    return $u;
}

function loginAs(?string $role = 'root'): User
{
    $u = makeServiceUser($role);
    Auth::login($u);

    return $u;
}

it('creates a user with hashed password and dispatches UserCreated', function (): void {
    Event::fake([UserCreated::class]);
    loginAs('root');

    $service = app(UserService::class);
    $user = $service->create([
        'first_name' => 'Alice',
        'last_name' => 'Doe',
        'email' => 'alice-create@x.test',
        'password' => 'secret123',
        'roles' => ['user'],
    ]);

    expect($user->email)->toBe('alice-create@x.test');
    expect(Hash::check('secret123', $user->password))->toBeTrue();
    expect($user->hasRole('user'))->toBeTrue();
    Event::assertDispatched(UserCreated::class);
});

it('updates a user profile and dispatches UserUpdated', function (): void {
    Event::fake([UserUpdated::class]);
    loginAs('root');

    $alice = makeServiceUser();
    $service = app(UserService::class);
    $updated = $service->update($alice, [
        'first_name' => 'Alicia',
        'phone' => '0123456789',
    ]);

    expect($updated->first_name)->toBe('Alicia');
    expect($updated->phone)->toBe('0123456789');
    Event::assertDispatched(UserUpdated::class);
});

it('does not change password when none is provided on update', function (): void {
    loginAs('root');
    $alice = makeServiceUser();
    $before = $alice->password;

    app(UserService::class)->update($alice, ['first_name' => 'A']);

    expect($alice->fresh()->password)->toBe($before);
});

it('rehashes password when explicitly provided', function (): void {
    loginAs('root');
    $alice = makeServiceUser();
    $before = $alice->password;

    app(UserService::class)->update($alice, ['password' => 'newpass123']);

    $after = $alice->fresh()->password;
    expect($after)->not->toBe($before);
    expect(Hash::check('newpass123', $after))->toBeTrue();
});

it('uploads an avatar and stores its path on the user', function (): void {
    loginAs('root');
    $alice = makeServiceUser();

    app(UserService::class)->update($alice, [
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ]);

    $alice->refresh();
    expect($alice->avatar_path)->not->toBeNull();
    Storage::disk('local')->assertExists($alice->avatar_path);
});

it('replaces and deletes the old avatar on a new upload', function (): void {
    loginAs('root');
    $alice = makeServiceUser();
    $svc = app(UserService::class);

    $svc->update($alice, ['avatar' => UploadedFile::fake()->image('first.jpg')]);
    $firstPath = $alice->fresh()->avatar_path;

    $svc->update($alice->fresh(), ['avatar' => UploadedFile::fake()->image('second.jpg')]);
    $secondPath = $alice->fresh()->avatar_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($secondPath);
});

it('removes the avatar when the removal flag is set', function (): void {
    loginAs('root');
    $alice = makeServiceUser();
    $svc = app(UserService::class);

    $svc->update($alice, ['avatar' => UploadedFile::fake()->image('me.jpg')]);
    $path = $alice->fresh()->avatar_path;

    $svc->update($alice->fresh(), ['removeAvatar' => true]);

    expect($alice->fresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

// Uploading a picture while a removal is marked must keep the picture: we do
// not delete what we have just replaced.
it('keeps a freshly uploaded avatar over a pending removal', function (): void {
    loginAs('root');
    $alice = makeServiceUser();
    $svc = app(UserService::class);

    $svc->update($alice, ['avatar' => UploadedFile::fake()->image('first.jpg')]);

    $svc->update($alice->fresh(), [
        'avatar' => UploadedFile::fake()->image('second.jpg'),
        'removeAvatar' => true,
    ]);

    expect($alice->fresh()->avatar_path)->not->toBeNull();
    Storage::disk('local')->assertExists($alice->fresh()->avatar_path);
});

it('leaves the avatar untouched when the flag is absent', function (): void {
    loginAs('root');
    $alice = makeServiceUser();
    $svc = app(UserService::class);

    $svc->update($alice, ['avatar' => UploadedFile::fake()->image('me.jpg')]);
    $path = $alice->fresh()->avatar_path;

    $svc->update($alice->fresh(), ['first_name' => 'Renommee']);

    expect($alice->fresh()->avatar_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
});

it('deletes the user, removes the avatar and dispatches UserDeleted', function (): void {
    Event::fake([UserDeleted::class]);
    loginAs('root');

    $alice = makeServiceUser();
    app(UserService::class)->update($alice, ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $path = $alice->fresh()->avatar_path;

    app(UserService::class)->delete($alice->fresh());

    expect(User::query()->find($alice->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
    Event::assertDispatched(UserDeleted::class);
});

it('forbids deleting a user with a higher role than the actor', function (): void {
    loginAs('administrateur');

    $rootTarget = makeServiceUser('root');

    expect(fn () => app(UserService::class)->delete($rootTarget))
        ->toThrow(AuthorizationException::class);
});

it('forbids updating a user with a higher role than the actor', function (): void {
    loginAs('administrateur');

    $rootTarget = makeServiceUser('root');

    expect(fn () => app(UserService::class)->update($rootTarget, ['first_name' => 'Hijack']))
        ->toThrow(AuthorizationException::class);
});

it('forbids an admin from demoting a root user via role sync', function (): void {
    loginAs('administrateur');

    $rootTarget = makeServiceUser('root');

    // The original attack: an admin tries to call update() with a roles array
    // that does NOT include 'root', effectively demoting the root user.
    expect(fn () => app(UserService::class)->update($rootTarget, ['roles' => ['administrateur']]))
        ->toThrow(AuthorizationException::class);

    expect($rootTarget->fresh()->hasRole('root'))->toBeTrue();
});

it('forbids assigning a role above the actor rank during create', function (): void {
    loginAs('administrateur');

    expect(fn () => app(UserService::class)->create([
        'first_name' => 'X',
        'last_name' => 'Y',
        'email' => 'admin-creates-root@x.test',
        'password' => 'secret123',
        'roles' => ['root'],
    ]))->toThrow(AuthorizationException::class);
});

it('syncs roles by replacing the previous set', function (): void {
    loginAs('root');
    $alice = makeServiceUser('user');

    app(UserService::class)->update($alice, ['roles' => ['administrateur']]);

    $alice->refresh();
    expect($alice->hasRole('administrateur'))->toBeTrue();
    expect($alice->hasRole('user'))->toBeFalse();
});

it('mirrors first_name + last_name into the legacy `name` column when present', function (): void {
    Schema::table('users', fn ($t) => $t->string('name')->nullable());
    loginAs('root');

    $alice = app(UserService::class)->create([
        'first_name' => 'Alice',
        'last_name' => 'Doe',
        'email' => 'name-col@x.test',
        'password' => 'secret123',
    ]);

    expect($alice->name)->toBe('Alice Doe');
    Schema::table('users', fn ($t) => $t->dropColumn('name'));
});
