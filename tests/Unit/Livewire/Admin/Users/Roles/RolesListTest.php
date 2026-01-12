<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Livewire\Admin\Users\Roles\RolesList;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

describe('RolesList', function () {
    describe('render', function () {
        it('renders the component', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RolesList::class)
                ->assertStatus(200);
        });

        it('displays roles list', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RolesList::class)
                ->assertSee('Root')
                ->assertSee('Admin');
        });
    });

    describe('getRoles', function () {
        it('returns paginated roles', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($root)->test(RolesList::class);

            $roles = $component->instance()->getRoles();

            expect($roles)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
                ->and($roles->count())->toBeGreaterThanOrEqual(7);
        });
    });

    describe('canEditRole', function () {
        it('allows root to edit roles', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::where('name', 'contributor')->first();

            $component = Livewire::actingAs($root)->test(RolesList::class);

            expect($component->instance()->canEditRole($role))->toBeTrue();
        });

        it('denies non-root users from editing roles', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $role = Role::where('name', 'contributor')->first();

            $component = Livewire::actingAs($admin)->test(RolesList::class);

            expect($component->instance()->canEditRole($role))->toBeFalse();
        });
    });

    describe('canDeleteRole', function () {
        it('allows root to delete non-protected roles', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::create(['name' => 'custom-deletable', 'label' => 'Custom Deletable', 'guard_name' => 'web']);

            $component = Livewire::actingAs($root)->test(RolesList::class);

            expect($component->instance()->canDeleteRole($role))->toBeTrue();
        });

        it('denies deletion of protected root role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $rootRole = Role::where('name', 'root')->first();

            $component = Livewire::actingAs($root)->test(RolesList::class);

            expect($component->instance()->canDeleteRole($rootRole))->toBeFalse();
        });

        it('denies deletion of protected admin role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $adminRole = Role::where('name', 'admin')->first();

            $component = Livewire::actingAs($root)->test(RolesList::class);

            expect($component->instance()->canDeleteRole($adminRole))->toBeFalse();
        });

        it('denies non-root users from deleting roles', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $role = Role::create(['name' => 'custom-role', 'label' => 'Custom Role', 'guard_name' => 'web']);

            $component = Livewire::actingAs($admin)->test(RolesList::class);

            expect($component->instance()->canDeleteRole($role))->toBeFalse();
        });
    });

    describe('deleteRole', function () {
        it('deletes a role when authorized', function () {
            Event::fake([RoleDeleted::class]);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::create(['name' => 'deletable-role', 'label' => 'Deletable Role', 'guard_name' => 'web']);

            Livewire::actingAs($root)
                ->test(RolesList::class)
                ->call('deleteRole', $role)
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('message', __('Role deleted successfully.'));

            expect(Role::where('name', 'deletable-role')->exists())->toBeFalse();
            Event::assertDispatched(RoleDeleted::class);
        });

        it('shows error when not authorized to delete', function () {
            $admin = User::factory()->create();
            $admin->assignRole(UserRoleEnum::ADMIN->value);

            $role = Role::create(['name' => 'another-role', 'label' => 'Another Role', 'guard_name' => 'web']);

            Livewire::actingAs($admin)
                ->test(RolesList::class)
                ->call('deleteRole', $role)
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('error', __('You are not authorized to delete this role.'));
        });

        it('shows error when trying to delete protected role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $rootRole = Role::where('name', 'root')->first();

            Livewire::actingAs($root)
                ->test(RolesList::class)
                ->call('deleteRole', $rootRole)
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('error', __('You are not authorized to delete this role.'));
        });
    });
});
