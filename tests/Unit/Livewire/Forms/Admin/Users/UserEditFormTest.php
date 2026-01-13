<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Livewire\Admin\Users\UserCreate;
use Carbon\Carbon;
use Livewire\Livewire;

describe('UserEditForm', function () {
    describe('setUser', function () {
        it('populates form with user data', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create([
                'name' => 'testuser',
                'email' => 'test@example.com',
                'civility' => 'M.',
                'profession' => 'Developer',
                'date_of_birth' => '1990-05-15',
            ]);
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $component = Livewire::actingAs($admin)
                ->test(\Arkhe\Main\Livewire\Admin\Users\UserEdit::class, ['user' => $user]);

            expect($component->get('userEditForm.name'))->toBe('testuser')
                ->and($component->get('userEditForm.email'))->toBe('test@example.com')
                ->and($component->get('userEditForm.civility'))->toBe('M.')
                ->and($component->get('userEditForm.profession'))->toBe('Developer')
                ->and($component->get('userEditForm.role'))->toBe(UserRoleEnum::CONTRIBUTOR->value);
        });

        it('loads roles relationship if not loaded', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::AUTHOR->value);

            // Get fresh user without loaded relationships
            $freshUser = User::find($user->id);

            $component = Livewire::actingAs($admin)
                ->test(\Arkhe\Main\Livewire\Admin\Users\UserEdit::class, ['user' => $freshUser]);

            expect($component->get('userEditForm.role'))->toBe(UserRoleEnum::AUTHOR->value);
        });
    });

    describe('rules', function () {
        it('requires password for new users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'newuser')
                ->set('userEditForm.email', 'new@example.com')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.password']);
        });

        it('does not require password for existing users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(\Arkhe\Main\Livewire\Admin\Users\UserEdit::class, ['user' => $user])
                ->set('userEditForm.name', 'updateduser')
                ->call('save')
                ->assertHasNoErrors(['userEditForm.password']);
        });

        it('validates email format', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.email', 'not-an-email')
                ->call('save')
                ->assertHasErrors(['userEditForm.email']);
        });

        it('validates password strength', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            // Too short
            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'test@example.com')
                ->set('userEditForm.password', 'short')
                ->set('userEditForm.password_confirmation', 'short')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.password']);

            // No uppercase
            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'test@example.com')
                ->set('userEditForm.password', 'lowercase123!')
                ->set('userEditForm.password_confirmation', 'lowercase123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.password']);
        });

        it('validates password confirmation', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'test@example.com')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'Different123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.password']);
        });
    });

    describe('toUserDtoArray', function () {
        it('converts form data to DTO array', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'dtouser')
                ->set('userEditForm.email', 'dto@example.com')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'Password123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->set('userEditForm.civility', 'Mme')
                ->set('userEditForm.profession', 'Engineer')
                ->set('userEditForm.date_of_birth', '1985-03-20');

            $dtoArray = $component->instance()->userEditForm->toUserDtoArray();

            expect($dtoArray['name'])->toBe('dtouser')
                ->and($dtoArray['email'])->toBe('dto@example.com')
                ->and($dtoArray['password'])->toBe('Password123!')
                ->and($dtoArray['role'])->toBe(UserRoleEnum::CONTRIBUTOR->value)
                ->and($dtoArray['civility'])->toBe('Mme')
                ->and($dtoArray['profession'])->toBe('Engineer')
                ->and($dtoArray['date_of_birth'])->toBeInstanceOf(Carbon::class);
        });

        it('handles null date_of_birth', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'nodateuser')
                ->set('userEditForm.email', 'nodate@example.com')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value);

            $dtoArray = $component->instance()->userEditForm->toUserDtoArray();

            expect($dtoArray['date_of_birth'])->toBeNull();
        });
    });
});
