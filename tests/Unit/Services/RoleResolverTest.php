<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Services\RoleResolver;
use Spatie\Permission\Models\Role;

describe('RoleResolver', function () {
    describe('protectedRoles / isProtected', function () {
        it('returns root and admin as protected', function () {
            expect(RoleResolver::protectedRoles())->toBe(['root', 'admin']);
        });

        it('considers root and admin protected', function () {
            expect(RoleResolver::isProtected('root'))->toBeTrue()
                ->and(RoleResolver::isProtected('admin'))->toBeTrue();
        });

        it('does not consider other roles protected', function () {
            expect(RoleResolver::isProtected('editorial'))->toBeFalse()
                ->and(RoleResolver::isProtected('treasurer'))->toBeFalse();
        });
    });

    describe('allowedRolesFor', function () {
        beforeEach(function () {
            foreach (UserRoleEnum::cases() as $case) {
                Role::firstOrCreate(['name' => $case->value, 'guard_name' => 'web'], ['label' => $case->label()]);
            }
        });

        it('returns full allowed list for a system role (enum)', function () {
            $user = User::factory()->create();
            $user->syncRoles(UserRoleEnum::EDITORIAL->value);

            $user->load('roles');

            expect(RoleResolver::allowedRolesFor($user))
                ->toBe(['editorial', 'author']);
        });

        it('returns GUEST when user has no role', function () {
            $user = User::factory()->create();
            $user->syncRoles([]);
            $user->load('roles');

            expect(RoleResolver::allowedRolesFor($user))
                ->toBe([UserRoleEnum::GUEST->value]);
        });

        it('reads config hierarchy for a custom role', function () {
            config(['arkhe.role_hierarchy.treasurer' => ['treasurer']]);
            Role::firstOrCreate(['name' => 'treasurer', 'guard_name' => 'web'], ['label' => 'Trésorier']);

            $user = User::factory()->create();
            $user->syncRoles('treasurer');

            $user->load('roles');

            expect(RoleResolver::allowedRolesFor($user))
                ->toBe(['treasurer']);
        });

        it('falls back to [roleName] for a custom role without config', function () {
            Role::firstOrCreate(['name' => 'mystery', 'guard_name' => 'web'], ['label' => 'Mystery']);

            $user = User::factory()->create();
            $user->syncRoles('mystery');

            $user->load('roles');

            expect(RoleResolver::allowedRolesFor($user))
                ->toBe(['mystery']);
        });
    });

    describe('label', function () {
        it('uses enum label for system roles', function () {
            expect(RoleResolver::label(UserRoleEnum::ROOT->value))
                ->toBe(UserRoleEnum::ROOT->label());
        });

        it('uses config label for a custom role', function () {
            config(['arkhe.role_labels.treasurer' => 'Trésorier']);

            expect(RoleResolver::label('treasurer'))->toBe('Trésorier');
        });

        it('humanizes the role name as fallback', function () {
            expect(RoleResolver::label('federation_manager'))
                ->toBe('Federation manager');
        });
    });
});
