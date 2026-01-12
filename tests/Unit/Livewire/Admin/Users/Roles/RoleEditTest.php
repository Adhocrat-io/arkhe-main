<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Arkhe\Main\Livewire\Admin\Users\Roles\RoleEdit;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('RoleEdit', function () {
    describe('mount', function () {
        it('loads existing role data into form', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::where('name', 'contributor')->first();

            $component = Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $role]);

            expect($component->get('roleEditForm.name'))->toBe('contributor')
                ->and($component->get('roleEditForm.label'))->toBe('Contributor')
                ->and($component->get('roleEditForm.guard_name'))->toBe('web');
        });

        it('initializes empty form for new role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($root)
                ->test(RoleEdit::class);

            expect($component->get('roleEditForm.name'))->toBe('')
                ->and($component->get('roleEditForm.label'))->toBe('');
        });

        it('loads all permissions', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Permission::firstOrCreate(['name' => 'test-permission', 'guard_name' => 'web']);

            $component = Livewire::actingAs($root)
                ->test(RoleEdit::class);

            expect($component->instance()->allPermissions)->not->toBeNull()
                ->and($component->instance()->allPermissions->count())->toBeGreaterThanOrEqual(1);
        });
    });

    describe('render', function () {
        it('renders the component for existing role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::where('name', 'contributor')->first();

            Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $role])
                ->assertStatus(200);
        });

        it('renders the component for new role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->assertStatus(200);
        });
    });

    describe('save', function () {
        it('creates a new role with valid data', function () {
            Event::fake([RoleCreated::class]);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', 'new-role')
                ->set('roleEditForm.label', 'New Role')
                ->set('roleEditForm.guard_name', 'web')
                ->call('save')
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('message', __('Role created successfully.'));

            expect(Role::where('name', 'new-role')->exists())->toBeTrue();
            Event::assertDispatched(RoleCreated::class);
        });

        it('updates an existing role', function () {
            Event::fake([RoleUpdated::class]);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::create(['name' => 'editable-role', 'label' => 'Old Label', 'guard_name' => 'web']);

            Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $role])
                ->set('roleEditForm.label', 'Updated Label')
                ->call('save')
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('message', __('Role updated successfully.'));

            $role->refresh();

            expect($role->label)->toBe('Updated Label');
            Event::assertDispatched(RoleUpdated::class);
        });

        it('creates role with permissions', function () {
            Event::fake([RoleCreated::class]);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $permission = Permission::firstOrCreate(['name' => 'test-perm', 'guard_name' => 'web']);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', 'role-with-perms')
                ->set('roleEditForm.label', 'Role With Perms')
                ->set('roleEditForm.guard_name', 'web')
                ->set('roleEditForm.permissions', [$permission->id => true])
                ->call('save')
                ->assertRedirect(route('admin.users.roles.index'));

            $newRole = Role::where('name', 'role-with-perms')->first();

            expect($newRole->hasPermissionTo('test-perm'))->toBeTrue();
        });

        it('fails validation with missing required fields', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', '')
                ->set('roleEditForm.label', '')
                ->call('save')
                ->assertHasErrors(['roleEditForm.name', 'roleEditForm.label']);
        });
    });

    describe('deleteRole', function () {
        it('deletes a non-protected role', function () {
            Event::fake([RoleDeleted::class]);

            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::create(['name' => 'delete-me', 'label' => 'Delete Me', 'guard_name' => 'web']);

            Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $role])
                ->call('deleteRole')
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('message', __('Role deleted successfully.'));

            expect(Role::where('name', 'delete-me')->exists())->toBeFalse();
            Event::assertDispatched(RoleDeleted::class);
        });

        it('shows error when role not found', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->call('deleteRole')
                ->assertRedirect(route('admin.users.roles.index'))
                ->assertSessionHas('error', __('Role not found.'));
        });

        it('does not delete protected role', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $rootRole = Role::where('name', 'root')->first();
            $rootRoleId = $rootRole->id;

            Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $rootRole])
                ->call('deleteRole')
                ->assertHasNoErrors();

            // Role should still exist
            expect(Role::find($rootRoleId))->not->toBeNull();
        });
    });
});
