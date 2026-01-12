<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Livewire\Admin\Users\UsersList;
use Livewire\Livewire;

describe('UsersList', function () {
    describe('render', function () {
        it('renders the component', function () {
            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($user)
                ->test(UsersList::class)
                ->assertStatus(200);
        });

        it('displays users list', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create(['username' => 'testuser']);
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(UsersList::class)
                ->assertSee('testuser');
        });
    });

    describe('search', function () {
        it('filters users by username', function () {
            $admin = User::factory()->create(['username' => 'adminuser']);
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user1 = User::factory()->create(['username' => 'findme']);
            $user1->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $user2 = User::factory()->create(['username' => 'hidden']);
            $user2->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(UsersList::class)
                ->set('search', 'findme')
                ->assertSee('findme')
                ->assertDontSee('hidden');
        });

        it('filters users by email', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $user = User::factory()->create(['email' => 'unique@test.com']);
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            Livewire::actingAs($admin)
                ->test(UsersList::class)
                ->set('search', 'unique@test.com')
                ->assertSee('unique@test.com');
        });
    });

    describe('role filter', function () {
        it('filters users by role', function () {
            $admin = User::factory()->create(['username' => 'rootadmin']);
            $admin->assignRole(UserRoleEnum::ROOT->value);

            $contributor = User::factory()->create(['username' => 'contribuser']);
            $contributor->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $author = User::factory()->create(['username' => 'authoruser']);
            $author->assignRole(UserRoleEnum::AUTHOR->value);

            Livewire::actingAs($admin)
                ->test(UsersList::class)
                ->set('role', UserRoleEnum::CONTRIBUTOR->value)
                ->assertSee('contribuser')
                ->assertDontSee('authoruser');
        });
    });

    describe('cleanSearchTerm', function () {
        it('cleans search term by trimming and removing tags', function () {
            $component = new UsersList;

            expect($component->cleanSearchTerm('  test  '))->toBe('test')
                ->and($component->cleanSearchTerm('<script>alert("xss")</script>'))->toBe('alert("xss")')
                ->and($component->cleanSearchTerm(str_repeat('a', 150)))->toHaveLength(100);
        });
    });

    describe('canEditUser', function () {
        it('allows root to edit any user', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::ADMIN->value);

            $this->actingAs($root);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canEditUser($targetUser))->toBeTrue();
        });

        it('allows admin to edit non-root users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $this->actingAs($admin);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canEditUser($targetUser))->toBeTrue();
        });

        it('denies admin from editing root users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $this->actingAs($admin);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canEditUser($root))->toBeFalse();
        });

        it('denies non-admin users from editing', function () {
            $contributor = User::factory()->create();
            $contributor->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::GUEST->value);

            $this->actingAs($contributor);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canEditUser($targetUser))->toBeFalse();
        });
    });

    describe('canDeleteUser', function () {
        it('allows root to delete other users', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $this->actingAs($root);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canDeleteUser($targetUser))->toBeTrue();
        });

        it('denies users from deleting themselves', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $this->actingAs($root);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canDeleteUser($root))->toBeFalse();
        });

        it('denies admin from deleting root users', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $this->actingAs($admin);

            $component = Livewire::test(UsersList::class);

            expect($component->instance()->canDeleteUser($root))->toBeFalse();
        });
    });

    describe('deleteUser', function () {
        it('deletes a user when authorized', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);
            $targetUserId = $targetUser->id;

            Livewire::actingAs($root)
                ->test(UsersList::class)
                ->call('deleteUser', $targetUserId)
                ->assertHasNoErrors();

            expect(User::find($targetUserId))->toBeNull();
        });

        it('does not delete when user not found', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(UsersList::class)
                ->call('deleteUser', 99999)
                ->assertHasNoErrors();
        });

        it('does not delete when not authorized', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);
            $rootId = $root->id;

            Livewire::actingAs($admin)
                ->test(UsersList::class)
                ->call('deleteUser', $rootId)
                ->assertHasNoErrors();

            // User should still exist
            expect(User::find($rootId))->not->toBeNull();
        });
    });
});
