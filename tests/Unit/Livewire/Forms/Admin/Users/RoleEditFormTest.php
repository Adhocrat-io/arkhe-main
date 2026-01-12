<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Livewire\Admin\Users\Roles\RoleEdit;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('RoleEditForm', function () {
    describe('setRole', function () {
        it('populates form with role data', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $role = Role::where('name', 'contributor')->first();

            $component = Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $role]);

            expect($component->get('roleEditForm.name'))->toBe('contributor')
                ->and($component->get('roleEditForm.label'))->toBe('Contributor')
                ->and($component->get('roleEditForm.guard_name'))->toBe('web');
        });

        it('loads role permissions', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $permission = Permission::firstOrCreate(['name' => 'test-role-perm', 'guard_name' => 'web']);
            $role = Role::create(['name' => 'role-with-perm', 'label' => 'Role With Perm', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);

            $component = Livewire::actingAs($root)
                ->test(RoleEdit::class, ['role' => $role]);

            $permissions = $component->get('roleEditForm.permissions');

            expect($permissions[$permission->id])->toBeTrue();
        });
    });

    describe('rules', function () {
        it('requires name field', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', '')
                ->set('roleEditForm.label', 'Test Label')
                ->call('save')
                ->assertHasErrors(['roleEditForm.name']);
        });

        it('requires label field', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', 'test-role')
                ->set('roleEditForm.label', '')
                ->call('save')
                ->assertHasErrors(['roleEditForm.label']);
        });

        it('validates max length for name', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', str_repeat('a', 300))
                ->set('roleEditForm.label', 'Test Label')
                ->call('save')
                ->assertHasErrors(['roleEditForm.name']);
        });

        it('validates max length for label', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', 'test-role')
                ->set('roleEditForm.label', str_repeat('a', 300))
                ->call('save')
                ->assertHasErrors(['roleEditForm.label']);
        });

        it('accepts nullable guard_name', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', 'nullable-guard-role')
                ->set('roleEditForm.label', 'Nullable Guard')
                ->set('roleEditForm.guard_name', '')
                ->call('save')
                ->assertHasNoErrors(['roleEditForm.guard_name']);
        });

        it('accepts array of permissions', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $permission = Permission::firstOrCreate(['name' => 'valid-perm', 'guard_name' => 'web']);

            Livewire::actingAs($root)
                ->test(RoleEdit::class)
                ->set('roleEditForm.name', 'role-with-valid-perms')
                ->set('roleEditForm.label', 'Role With Valid Perms')
                ->set('roleEditForm.permissions', [$permission->id => true])
                ->call('save')
                ->assertHasNoErrors(['roleEditForm.permissions']);
        });
    });

    describe('messages', function () {
        it('returns custom validation messages', function () {
            $root = User::factory()->create();
            $root->assignRole(UserRoleEnum::ROOT->value);

            $component = Livewire::actingAs($root)
                ->test(RoleEdit::class);

            $messages = $component->instance()->roleEditForm->messages();

            expect($messages)->toHaveKey('label.required')
                ->and($messages)->toHaveKey('name.required')
                ->and($messages['label.required'])->toBe(__('The label is required.'))
                ->and($messages['name.required'])->toBe(__('The name is required.'));
        });
    });
});
