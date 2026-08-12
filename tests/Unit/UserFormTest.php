<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\Forms\UserForm;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();

    // ListUsers::mount() now calls $this->authorize('view-user'),
    // so instantiating the component requires an authenticated user
    // with that permission. A root user satisfies it via the seeder.
    /** @var User $admin */
    $admin = User::query()->forceCreate([
        'first_name' => 'Form',
        'last_name'  => 'Fixture',
        'email'      => 'form-fixture@x.test',
        'password'   => Hash::make('secret123'),
    ]);
    $admin->assignRole('root');

    Livewire::actingAs($admin);
});

function instantiateUserForm(): UserForm
{
    // UserForm requires a Livewire component + property name in its ctor.
    return new UserForm(
        Livewire::test(ListUsers::class)->instance(),
        'userForm',
    );
}

it('produces required + min + confirmed-equivalent password rules on create', function (): void {
    $form         = instantiateUserForm();
    $form->id     = null;

    $rules = $form->rules();

    expect($rules['password'])->toBeArray();
    // First element should be "required" when creating from scratch.
    expect($rules['password'][0])->toBe('required');
    expect($rules['password'])->toContain('min:8');
});

it('returns empty password rules when editing with no new value', function (): void {
    $form           = instantiateUserForm();
    $form->id       = 42;
    $form->password = '';

    expect($form->rules()['password'])->toBe([]);
});

it('returns strength rules when editing with a new password', function (): void {
    $form           = instantiateUserForm();
    $form->id       = 42;
    $form->password = 'newpass123';

    $rules = $form->rules()['password'];

    expect($rules)->toContain('string');
    expect($rules)->toContain('min:8');
});

it('hydrates from an existing user via fillFromModel', function (): void {
    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Luc',
        'last_name'  => 'Adhocrat',
        'email'      => 'luc-form@x.test',
        'phone'      => '01-23',
        'civility'   => 'Mr',
        'password'   => Hash::make('x'),
    ]);
    $user->assignRole('administrateur');

    $form = instantiateUserForm();
    $form->fillFromModel($user);

    expect($form->id)->toBe($user->id);
    expect($form->first_name)->toBe('Luc');
    expect($form->last_name)->toBe('Adhocrat');
    expect($form->email)->toBe('luc-form@x.test');
    expect($form->password)->toBe('');
    expect($form->passwordConfirmation)->toBe('');
    expect($form->phone)->toBe('01-23');
    expect($form->civility)->toBe('Mr');
    expect($form->role)->toBe('administrateur');
});

it('exposes form state as an array suitable for the service', function (): void {
    $form             = instantiateUserForm();
    $form->first_name = 'A';
    $form->last_name  = 'B';
    $form->email      = 'ab@x.test';
    $form->password   = 'pw';
    $form->role       = 'user';

    $arr = $form->toPayload();

    expect($arr['first_name'])->toBe('A');
    expect($arr['email'])->toBe('ab@x.test');
    // role → roles array for the service.
    expect($arr['role'])->toBe('user');
    expect($arr['roles'])->toBe(['user']);
});

it('emits an empty roles array when no role is selected', function (): void {
    $form       = instantiateUserForm();
    $form->role = null;

    expect($form->toPayload()['roles'])->toBe([]);

    $form->role = '';
    expect($form->toPayload()['roles'])->toBe([]);
});
