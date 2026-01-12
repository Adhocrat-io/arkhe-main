<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Policies\UserPolicy;

beforeEach(function () {
    $this->policy = new UserPolicy();
});

describe('UserPolicy', function () {
    describe('viewAny', function () {
        it('allows root users to view any users', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            expect($this->policy->viewAny($rootUser))->toBeTrue();
        });

        it('allows admin users to view any users', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            expect($this->policy->viewAny($adminUser))->toBeTrue();
        });

        it('denies non-admin users to view any users', function () {
            $regularUser = User::factory()->create();
            $regularUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            expect($this->policy->viewAny($regularUser))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows root to view any user', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::ADMIN->value);

            expect($this->policy->view($rootUser, $targetUser))->toBeTrue();
        });

        it('allows admin to view non-root users', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            expect($this->policy->view($adminUser, $targetUser))->toBeTrue();
        });

        it('denies admin to view root users', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            expect($this->policy->view($adminUser, $rootUser))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('denies user to delete themselves', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            expect($this->policy->delete($rootUser, $rootUser))->toBeFalse();
        });

        it('allows root to delete other users', function () {
            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            $targetUser = User::factory()->create();
            $targetUser->assignRole(UserRoleEnum::ADMIN->value);

            expect($this->policy->delete($rootUser, $targetUser))->toBeTrue();
        });

        it('denies admin to delete root users', function () {
            $adminUser = User::factory()->create();
            $adminUser->assignRole(UserRoleEnum::ADMIN->value);

            $rootUser = User::factory()->create();
            $rootUser->assignRole(UserRoleEnum::ROOT->value);

            expect($this->policy->delete($adminUser, $rootUser))->toBeFalse();
        });
    });
});
