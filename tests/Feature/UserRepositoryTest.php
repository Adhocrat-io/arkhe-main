<?php

declare(strict_types=1);

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

function seedRepoUser(array $attrs = [], ?string $role = null): User
{
    /** @var User $u */
    $u = User::query()->forceCreate(array_merge([
        'first_name' => 'X',
        'last_name'  => 'Y',
        'email'      => 'rep-'.uniqid().'@x.test',
        'password'   => Hash::make('x'),
    ], $attrs));

    if ($role !== null) {
        $u->assignRole($role);
    }

    return $u;
}

it('returns a paginator of users', function (): void {
    foreach (range(1, 5) as $_) {
        seedRepoUser();
    }

    $page = app(UserRepositoryInterface::class)->paginate([], 'created_at', 'desc', 3);

    expect($page->total())->toBe(5);
    expect($page->count())->toBe(3);
});

it('finds a user by id', function (): void {
    $u = seedRepoUser();

    expect(app(UserRepositoryInterface::class)->find($u->id)->email)->toBe($u->email);
    expect(app(UserRepositoryInterface::class)->find(999))->toBeNull();
});

it('filters users by search across first_name, last_name, email', function (): void {
    seedRepoUser(['first_name' => 'Alice', 'email' => 'a@x.test']);
    seedRepoUser(['last_name'  => 'Carpenter', 'email' => 'b@x.test']);
    seedRepoUser(['email' => 'charlie@x.test']);

    $repo = app(UserRepositoryInterface::class);

    expect($repo->paginate(['search' => 'Alice'])->total())->toBe(1);
    expect($repo->paginate(['search' => 'Carpenter'])->total())->toBe(1);
    expect($repo->paginate(['search' => 'charlie'])->total())->toBe(1);
    expect($repo->paginate(['search' => 'nope'])->total())->toBe(0);
});

it('filters users by role', function (): void {
    seedRepoUser([], 'root');
    seedRepoUser([], 'administrateur');
    seedRepoUser([], 'user');
    seedRepoUser([], 'user');

    $repo = app(UserRepositoryInterface::class);

    expect($repo->paginate(['role' => 'user'])->total())->toBe(2);
    expect($repo->paginate(['role' => 'root'])->total())->toBe(1);
});

it('orders by the requested field and direction', function (): void {
    seedRepoUser(['last_name' => 'A']);
    seedRepoUser(['last_name' => 'B']);
    seedRepoUser(['last_name' => 'C']);

    $repo = app(UserRepositoryInterface::class);

    $asc  = $repo->paginate([], 'last_name', 'asc')->items();
    $desc = $repo->paginate([], 'last_name', 'desc')->items();

    expect($asc[0]->last_name)->toBe('A');
    expect($desc[0]->last_name)->toBe('C');
});

it('returns a fresh user model instance from newModel()', function (): void {
    $model = app(UserRepositoryInterface::class)->newModel();

    expect($model)->toBeInstanceOf(User::class);
    expect($model->exists)->toBeFalse();
});
