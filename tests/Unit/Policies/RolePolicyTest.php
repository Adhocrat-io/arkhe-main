<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Policies\RolePolicy;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->policy = new RolePolicy();
});

describe('RolePolicy', function () {
    describe('viewAny', function () {
        it('allows root users to view any roles', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            expect($this->policy->viewAny($rootUser))->toBeTrue();
        });

        it('denies admin users to view roles', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            expect($this->policy->viewAny($adminUser))->toBeFalse();
        });

        it('denies non-root users to view roles', function () {
            $regularUser = User::factory()->create();
            $regularUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            expect($this->policy->viewAny($regularUser))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows root users to create roles', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            expect($this->policy->create($rootUser))->toBeTrue();
        });

        it('denies non-root users to create roles', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            expect($this->policy->create($adminUser))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('denies deletion of protected root role', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            $rootRole = Role::where('name', 'root')->first();

            expect($this->policy->delete($rootUser, $rootRole))->toBeFalse();
        });

        it('denies deletion of protected admin role', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            $adminRole = Role::where('name', 'admin')->first();

            expect($this->policy->delete($rootUser, $adminRole))->toBeFalse();
        });

        it('allows root to delete non-protected roles', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            $customRole = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

            expect($this->policy->delete($rootUser, $customRole))->toBeTrue();
        });

        it('denies non-root users to delete any role', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            $customRole = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

            expect($this->policy->delete($adminUser, $customRole))->toBeFalse();
        });
    });
});
