<?php

declare(strict_types=1);

use Adhocrat\Arkhe\Database\Seeders\ArkheRolesSeeder;
use Adhocrat\Arkhe\Livewire\ListUsers;
use Adhocrat\Arkhe\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
});

function makeUser(array $attrs = [], ?string $role = null): User
{
    /** @var User $user */
    $user = User::query()->forceCreate(array_merge([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'test'.uniqid().'@example.test',
        'password'   => Hash::make('secret123'),
    ], $attrs));

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

it('allows a root user to view the list', function (): void {
    $user = makeUser([], 'root');

    Livewire::actingAs($user)
        ->test(ListUsers::class)
        ->assertStatus(200);
});

it('allows an administrateur to view the list', function (): void {
    $user = makeUser([], 'administrateur');

    Livewire::actingAs($user)
        ->test(ListUsers::class)
        ->assertStatus(200);
});

it('forbids a regular user via middleware', function (): void {
    $user = makeUser([], 'user');

    $this->actingAs($user)
        ->get('/administration/users')
        ->assertForbidden();
});

it('forbids a guest-role user via middleware', function (): void {
    $user = makeUser([], 'guest');

    $this->actingAs($user)
        ->get('/administration/users')
        ->assertForbidden();
});

it('redirects anonymous visitors to login', function (): void {
    $this->get('/administration/users')
        ->assertRedirect();
});

it('creates a user through the Livewire form', function (): void {
    $root = makeUser([], 'root');

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->call('openCreate')
        ->set('userForm.first_name', 'Alice')
        ->set('userForm.last_name', 'Doe')
        ->set('userForm.email', 'alice@example.test')
        ->set('userForm.password', 'password123')
        ->set('userForm.roles', ['user'])
        ->call('save')
        ->assertHasNoErrors();

    $alice = User::query()->where('email', 'alice@example.test')->first();
    expect($alice)->not->toBeNull();
    expect($alice->hasRole('user'))->toBeTrue();
});

it('edits an existing user', function (): void {
    $root  = makeUser([], 'root');
    $alice = makeUser(['first_name' => 'Alice', 'email' => 'alice@example.test'], 'user');

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->call('openEdit', $alice->id)
        ->set('userForm.first_name', 'Alicia')
        ->call('save')
        ->assertHasNoErrors();

    expect($alice->fresh()->first_name)->toBe('Alicia');
});

it('deletes a user after confirmation', function (): void {
    $root  = makeUser([], 'root');
    $alice = makeUser([], 'user');

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->call('confirmDelete', $alice->id)
        ->call('delete');

    expect(User::query()->find($alice->id))->toBeNull();
});

it('searches users by first_name, last_name and email', function (): void {
    $root = makeUser([], 'root');
    makeUser(['first_name' => 'Alice', 'email' => 'alice@example.test']);
    makeUser(['last_name'  => 'Carpenter', 'email' => 'bob@example.test']);
    makeUser(['email'      => 'charlie@example.test']);

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->set('search', 'Alice')
        ->assertSee('alice@example.test')
        ->assertDontSee('bob@example.test');

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->set('search', 'Carpenter')
        ->assertSee('bob@example.test')
        ->assertDontSee('alice@example.test');

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->set('search', 'charlie')
        ->assertSee('charlie@example.test');
});

it('toggles sort direction on the same field', function (): void {
    $root = makeUser([], 'root');

    $component = Livewire::actingAs($root)->test(ListUsers::class);

    $component->call('sortBy', 'last_name')
        ->assertSet('sortField', 'last_name')
        ->assertSet('sortDirection', 'asc');

    $component->call('sortBy', 'last_name')
        ->assertSet('sortDirection', 'desc');
});

it('respects the configured per_page', function (): void {
    config()->set('arkhe.per_page', 5);

    $root = makeUser([], 'root');
    for ($i = 0; $i < 12; $i++) {
        makeUser();
    }

    Livewire::actingAs($root)
        ->test(ListUsers::class)
        ->assertSet('perPage', 5);
});
