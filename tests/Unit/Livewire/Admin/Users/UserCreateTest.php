<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Events\UserCreated;
use Arkhe\Main\Livewire\Admin\Users\UserCreate;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

describe('UserCreate', function () {
    describe('render', function () {
        it('renders the component', function () {
            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->assertStatus(200);
        });
    });

    describe('getRoles', function () {
        it('returns all roles', function () {
            $admin = User::factory()->root()->create();

            $component = Livewire::actingAs($admin)->test(UserCreate::class);

            $roles = $component->instance()->getRoles();

            expect($roles->count())->toBeGreaterThanOrEqual(7);
        });
    });

    describe('save', function () {
        it('creates a new user with valid data', function () {
            Event::fake([UserCreated::class]);

            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'newuser')
                ->set('userEditForm.email', 'newuser@gmail.com')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'Password123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasNoErrors();

            expect(User::where('email', 'newuser@gmail.com')->exists())->toBeTrue();
            Event::assertDispatched(UserCreated::class);
        });

        it('fails validation with missing required fields', function () {
            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', '')
                ->set('userEditForm.email', '')
                ->call('save')
                ->assertHasErrors(['userEditForm.name', 'userEditForm.email']);
        });

        it('fails validation with invalid email', function () {
            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'invalid-email')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'Password123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.email']);
        });

        it('fails validation with weak password', function () {
            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'test@example.com')
                ->set('userEditForm.password', 'weak')
                ->set('userEditForm.password_confirmation', 'weak')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.password']);
        });

        it('fails validation with mismatched password confirmation', function () {
            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'test@example.com')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'DifferentPassword123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.password']);
        });

        it('fails validation with duplicate email', function () {
            $admin = User::factory()->root()->create();
            User::factory()->create(['email' => 'existing@example.com']);

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'testuser')
                ->set('userEditForm.email', 'existing@example.com')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'Password123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->call('save')
                ->assertHasErrors(['userEditForm.email']);
        });

        it('creates user with optional fields', function () {
            Event::fake([UserCreated::class]);

            $admin = User::factory()->root()->create();

            Livewire::actingAs($admin)
                ->test(UserCreate::class)
                ->set('userEditForm.name', 'fulluser')
                ->set('userEditForm.email', 'fulluser@gmail.com')
                ->set('userEditForm.password', 'Password123!')
                ->set('userEditForm.password_confirmation', 'Password123!')
                ->set('userEditForm.role', UserRoleEnum::CONTRIBUTOR->value)
                ->set('userEditForm.civility', 'M.')
                ->set('userEditForm.profession', 'Developer')
                ->set('userEditForm.date_of_birth', '1990-01-15')
                ->call('save')
                ->assertHasNoErrors();

            $user = User::where('email', 'fulluser@gmail.com')->first();

            expect($user->civility)->toBe('M.')
                ->and($user->profession)->toBe('Developer')
                ->and($user->date_of_birth->format('Y-m-d'))->toBe('1990-01-15');
        });
    });
});
