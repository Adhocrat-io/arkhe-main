<?php

use App\Livewire\Settings\DeleteUserForm;
use App\Livewire\Settings\Profile;
use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnum::ADMIN->value);
    $this->actingAs($user);

    $this->get(route('admin.settings.profile'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnum::ADMIN->value);
    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->full_name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnum::ADMIN->value);
    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnum::ADMIN->value);
    $this->actingAs($user);

    $response = Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('front.home'));

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRoleEnum::ADMIN->value);
    $this->actingAs($user);

    $response = Livewire::test(DeleteUserForm::class)
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
