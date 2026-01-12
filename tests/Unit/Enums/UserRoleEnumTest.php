<?php

declare(strict_types=1);

use Arkhe\Main\Enums\Users\UserRoleEnum;

describe('UserRoleEnum', function () {
    describe('values', function () {
        it('has correct values', function () {
            expect(UserRoleEnum::ROOT->value)->toBe('root')
                ->and(UserRoleEnum::ADMIN->value)->toBe('admin')
                ->and(UserRoleEnum::EDITORIAL->value)->toBe('editorial')
                ->and(UserRoleEnum::AUTHOR->value)->toBe('author')
                ->and(UserRoleEnum::CONTRIBUTOR->value)->toBe('contributor')
                ->and(UserRoleEnum::SUBSCRIBER->value)->toBe('subscriber')
                ->and(UserRoleEnum::GUEST->value)->toBe('guest');
        });
    });

    describe('label', function () {
        it('returns translated labels', function () {
            expect(UserRoleEnum::ROOT->label())->toBeString()
                ->and(UserRoleEnum::ADMIN->label())->toBeString()
                ->and(UserRoleEnum::GUEST->label())->toBeString();
        });
    });

    describe('getAllowedRoles', function () {
        it('returns all roles for root', function () {
            $allowedRoles = UserRoleEnum::ROOT->getAllowedRoles();

            expect($allowedRoles)->toContain('root')
                ->and($allowedRoles)->toContain('admin')
                ->and($allowedRoles)->toContain('contributor')
                ->and($allowedRoles)->toContain('guest');
        });

        it('returns non-root roles for admin', function () {
            $allowedRoles = UserRoleEnum::ADMIN->getAllowedRoles();

            expect($allowedRoles)->not->toContain('root')
                ->and($allowedRoles)->toContain('admin')
                ->and($allowedRoles)->toContain('contributor');
        });

        it('returns limited roles for contributor', function () {
            $allowedRoles = UserRoleEnum::CONTRIBUTOR->getAllowedRoles();

            expect($allowedRoles)->not->toContain('root')
                ->and($allowedRoles)->not->toContain('admin');
        });
    });

    describe('tryFrom', function () {
        it('returns enum for valid value', function () {
            $enum = UserRoleEnum::tryFrom('root');

            expect($enum)->toBe(UserRoleEnum::ROOT);
        });

        it('returns null for invalid value', function () {
            $enum = UserRoleEnum::tryFrom('invalid');

            expect($enum)->toBeNull();
        });
    });
});
