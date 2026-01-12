<?php

declare(strict_types=1);

use Arkhe\Main\DataTransferObjects\RoleDto;
use Arkhe\Main\Events\RoleCreated;
use Arkhe\Main\Events\RoleDeleted;
use Arkhe\Main\Events\RoleUpdated;
use Arkhe\Main\Repositories\RoleRepository;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->repository = new RoleRepository;
});

describe('RoleRepository', function () {
    describe('create', function () {
        it('creates a role with permissions', function () {
            Event::fake([RoleCreated::class]);

            Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);

            $roleDto = new RoleDto(
                name: 'test-role',
                label: 'Test Role',
                guard_name: 'web',
                permissions: ['test-permission'],
            );

            $role = $this->repository->create($roleDto);

            expect($role)->toBeInstanceOf(Role::class)
                ->and($role->name)->toBe('test-role')
                ->and($role->label)->toBe('Test Role')
                ->and($role->hasPermissionTo('test-permission'))->toBeTrue();

            Event::assertDispatched(RoleCreated::class, function ($event) use ($role) {
                return $event->role->id === $role->id;
            });
        });
    });

    describe('update', function () {
        it('updates a role and syncs permissions', function () {
            Event::fake([RoleUpdated::class]);

            Permission::create(['name' => 'perm-1', 'guard_name' => 'web']);
            Permission::create(['name' => 'perm-2', 'guard_name' => 'web']);

            $role = Role::create(['name' => 'updatable-role', 'label' => 'Old Label', 'guard_name' => 'web']);
            $role->givePermissionTo('perm-1');

            $roleDto = new RoleDto(
                name: 'updatable-role',
                label: 'New Label',
                guard_name: 'web',
                permissions: ['perm-2'],
            );

            $updatedRole = $this->repository->update($role, $roleDto);

            expect($updatedRole->label)->toBe('New Label')
                ->and($updatedRole->hasPermissionTo('perm-2'))->toBeTrue()
                ->and($updatedRole->hasPermissionTo('perm-1'))->toBeFalse();

            Event::assertDispatched(RoleUpdated::class);
        });
    });

    describe('delete', function () {
        it('deletes a non-protected role', function () {
            Event::fake([RoleDeleted::class]);

            $role = Role::create(['name' => 'deletable-role', 'label' => 'Deletable Role', 'guard_name' => 'web']);
            $roleId = $role->id;

            $this->repository->delete($role);

            expect(Role::find($roleId))->toBeNull();
            Event::assertDispatched(RoleDeleted::class);
        });

        it('throws exception when deleting protected root role', function () {
            $rootRole = Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

            $this->repository->delete($rootRole);
        })->throws(\RuntimeException::class);

        it('throws exception when deleting protected admin role', function () {
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

            $this->repository->delete($adminRole);
        })->throws(\RuntimeException::class);
    });

    describe('isProtectedRole', function () {
        it('identifies root as protected', function () {
            $role = Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

            expect($this->repository->isProtectedRole($role))->toBeTrue();
        });

        it('identifies admin as protected', function () {
            $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

            expect($this->repository->isProtectedRole($role))->toBeTrue();
        });

        it('identifies custom roles as non-protected', function () {
            $role = Role::create(['name' => 'custom', 'label' => 'Custom', 'guard_name' => 'web']);

            expect($this->repository->isProtectedRole($role))->toBeFalse();
        });
    });

    describe('getAllRoles', function () {
        it('returns all roles', function () {
            Role::create(['name' => 'role-1', 'label' => 'Role 1', 'guard_name' => 'web']);
            Role::create(['name' => 'role-2', 'label' => 'Role 2', 'guard_name' => 'web']);

            $roles = $this->repository->getAllRoles();

            expect($roles->count())->toBeGreaterThanOrEqual(2);
        });
    });
});
