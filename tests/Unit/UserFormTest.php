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

// A closure rule bypasses Laravel's attribute resolution: the raw name landed
// in the message, so a French install read "Le champ de confirmation password
// ne correspond pas". Testbench ships no framework translations, so the test
// registers both the message and the attribute it must interpolate.
it('resolves the attribute name through the app dictionary on a mismatch', function (): void {
    app('translator')->addLines([
        'validation.confirmed' => 'The :attribute confirmation does not match.',
        'validation.attributes.password' => 'mot de passe',
    ], app()->getLocale());

    $form = instantiateUserForm();
    $form->id = null;
    $form->password = 'secret123';
    $form->passwordConfirmation = 'something-else';

    $failures = [];
    foreach ($form->rules()['password'] as $rule) {
        if ($rule instanceof Closure) {
            $rule('password', 'secret123', function (string $message) use (&$failures): void {
                $failures[] = $message;
            });
        }
    }

    expect($failures)->not->toBeEmpty()
        ->and($failures[0])->toBe('The mot de passe confirmation does not match.');
});

// No entry in the dictionary: fall back to the humanised name rather than the
// camelCase property.
it('falls back to the humanised attribute when no translation exists', function (): void {
    app('translator')->addLines([
        'validation.confirmed' => 'The :attribute confirmation does not match.',
    ], app()->getLocale());

    $form = instantiateUserForm();
    $form->id = null;
    $form->password = 'secret123';
    $form->passwordConfirmation = 'something-else';

    $failures = [];
    foreach ($form->rules()['password'] as $rule) {
        if ($rule instanceof Closure) {
            $rule('passwordConfirmation', 'secret123', function (string $message) use (&$failures): void {
                $failures[] = $message;
            });
        }
    }

    expect($failures[0])->toBe('The password confirmation confirmation does not match.');
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

    $arr = $form->toArray();

    expect($arr['first_name'])->toBe('A');
    expect($arr['email'])->toBe('ab@x.test');
    // role → roles array for the service.
    expect($arr['role'])->toBe('user');
    expect($arr['roles'])->toBe(['user']);
});

it('emits an empty roles array when no role is selected', function (): void {
    $form       = instantiateUserForm();
    $form->role = null;

    expect($form->toArray()['roles'])->toBe([]);

    $form->role = '';
    expect($form->toArray()['roles'])->toBe([]);
});
