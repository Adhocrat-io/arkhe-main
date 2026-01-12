<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Events\UserDeleted;
use Arkhe\Main\Events\UserUpdated;
use Arkhe\Main\Livewire\Admin\Users\UserEdit;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

describe('UserEdit', function () {
    describe('mount', function () {
        it('loads user data into form', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create([
                'username' => 'testuser',
                'email' => 'test@example.com',
                'civility' => 'M.',
                'profession' => 'Developer',
            ]);
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $component = Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user]);

            expect($component->get('userEditForm.username'))->toBe('testuser')
                ->and($component->get('userEditForm.email'))->toBe('test@example.com')
                ->and($component->get('userEditForm.civility'))->toBe('M.')
                ->and($component->get('userEditForm.profession'))->toBe('Developer')
                ->and($component->get('userEditForm.role'))->toBe(UserRoleEnum::CONTRIBUTOR->value);
        });
    });

    describe('render', function () {
        it('renders the component', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user])
                ->assertStatus(200);
        });
    });

    describe('canEditUser', function () {
        it('allows root to edit any user', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::ADMIN->value);

            $component = Livewire::actingAs($root)
                ->test(UserEdit::class, ['user' => $targetUser]);

            expect($component->instance()->canEditUser($targetUser))->toBeTrue();
        });

        it('allows admin to edit non-root users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $component = Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $targetUser]);

            expect($component->instance()->canEditUser($targetUser))->toBeTrue();
        });

        it('denies admin from editing root users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $root]);

            expect($component->instance()->canEditUser($root))->toBeFalse();
        });

        it('denies non-admin users from editing', function () {
            $contributor = User::factory()->create();
            $contributor->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::GUEST->value);

            $component = Livewire::actingAs($contributor)
                ->test(UserEdit::class, ['user' => $targetUser]);

            expect($component->instance()->canEditUser($targetUser))->toBeFalse();
        });
    });

    describe('canDeleteUser', function () {
        it('allows root to delete other users', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $component = Livewire::actingAs($root)
                ->test(UserEdit::class, ['user' => $targetUser]);

            expect($component->instance()->canDeleteUser($targetUser))->toBeTrue();
        });

        it('denies users from deleting themselves', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($root)
                ->test(UserEdit::class, ['user' => $root]);

            expect($component->instance()->canDeleteUser($root))->toBeFalse();
        });

        it('denies admin from deleting root users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $root]);

            expect($component->instance()->canDeleteUser($root))->toBeFalse();
        });
    });

    describe('save', function () {
        it('updates user with valid data', function () {
            Event::fake([UserUpdated::class]);

            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create([
                'username' => 'oldname',
                'email' => 'old@gmail.com',
            ]);
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user])
                ->set('userEditForm.username', 'newname')
                ->set('userEditForm.email', 'new@gmail.com')
                ->set('userEditForm.role', UserRoleEnum::AUTHOR->value)
                ->call('save')
                ->assertHasNoErrors();

            $user->refresh();

            expect($user->username)->toBe('newname')
                ->and($user->email)->toBe('new@gmail.com')
                ->and($user->hasRole(UserRoleEnum::AUTHOR->value))->toBeTrue();

            Event::assertDispatched(UserUpdated::class);
        });

        it('does not update when not authorized to edit', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create(['username' => 'rootuser']);
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $root])
                ->call('save')
                ->assertHasNoErrors();

            // Username should remain unchanged
            $root->refresh();
            expect($root->username)->toBe('rootuser');
        });

        it('fails validation with invalid data', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user])
                ->set('userEditForm.username', '')
                ->set('userEditForm.email', 'invalid-email')
                ->call('save')
                ->assertHasErrors(['userEditForm.username', 'userEditForm.email']);
        });

        it('updates password when provided', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);
            $oldPasswordHash = $user->password;

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user])
                ->set('userEditForm.password', 'NewPassword123!')
                ->set('userEditForm.password_confirmation', 'NewPassword123!')
                ->call('save')
                ->assertHasNoErrors();

            $user->refresh();

            expect($user->password)->not->toBe($oldPasswordHash);
        });

        it('does not update password when not provided', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);
            $oldPasswordHash = $user->password;

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user])
                ->set('userEditForm.username', 'updatedname')
                ->call('save')
                ->assertHasNoErrors();

            $user->refresh();

            expect($user->password)->toBe($oldPasswordHash);
        });
    });

    describe('deleteUser', function () {
        it('deletes user when authorized', function () {
            Event::fake([UserDeleted::class]);

            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);
            $userId = $user->id;

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $user])
                ->call('deleteUser')
                ->assertRedirect(route('admin.users.index'))
                ->assertSessionHas('message', __('User deleted successfully.'));

            expect(User::find($userId))->toBeNull();
            Event::assertDispatched(UserDeleted::class);
        });

        it('shows error when not authorized to delete', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($admin)
                ->test(UserEdit::class, ['user' => $root])
                ->call('deleteUser')
                ->assertRedirect(route('admin.users.index'))
                ->assertSessionHas('error', __('You are not authorized to delete this user.'));
        });
    });
});
