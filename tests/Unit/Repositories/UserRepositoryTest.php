<?php

declare(strict_types=1);

use App\Models\User;
use Arkhe\Main\DataTransferObjects\UserDto;
use Arkhe\Main\Enums\Users\UserRoleEnum;
use Arkhe\Main\Events\UserCreated;
use Arkhe\Main\Events\UserDeleted;
use Arkhe\Main\Events\UserUpdated;
use Arkhe\Main\Repositories\UserRepository;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->repository = new UserRepository();
});

describe('UserRepository', function () {
    describe('create', function () {
        it('creates a user with role', function () {
            Event::fake([UserCreated::class]);

            $userDto = new UserDto(
                first_name: 'John',
                last_name: 'Doe',
                email: 'john@example.com',
                date_of_birth: null,
                civility: 'M',
                profession: 'Developer',
                password: 'password123',
                role: UserRoleEnum::CONTRIBUTOR->value,
            );

            $user = $this->repository->create($userDto);

            expect($user)->toBeInstanceOf(User::class)
                ->and($user->first_name)->toBe('John')
                ->and($user->last_name)->toBe('Doe')
                ->and($user->email)->toBe('john@example.com')
                ->and($user->hasRole(UserRoleEnum::CONTRIBUTOR->value))->toBeTrue();

            Event::assertDispatched(UserCreated::class, function ($event) use ($user) {
                return $event->user->id === $user->id;
            });
        });
    });

    describe('update', function () {
        it('updates a user and syncs role', function () {
            Event::fake([UserUpdated::class]);

            $user = User::factory()->create([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ]);
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);

            $userDto = new UserDto(
                first_name: 'Jane Updated',
                last_name: 'Smith Updated',
                email: $user->email,
                date_of_birth: null,
                civility: 'Mme',
                profession: 'Manager',
                password: null,
                role: UserRoleEnum::AUTHOR->value,
            );

            $updatedUser = $this->repository->update($user, $userDto);

            expect($updatedUser->first_name)->toBe('Jane Updated')
                ->and($updatedUser->last_name)->toBe('Smith Updated')
                ->and($updatedUser->hasRole(UserRoleEnum::AUTHOR->value))->toBeTrue()
                ->and($updatedUser->hasRole(UserRoleEnum::CONTRIBUTOR->value))->toBeFalse();

            Event::assertDispatched(UserUpdated::class, function ($event) use ($user) {
                return $event->user->id === $user->id;
            });
        });

        it('does not update password when null', function () {
            $user = User::factory()->create();
            $originalPasswordHash = $user->password;

            $userDto = new UserDto(
                first_name: 'Updated',
                last_name: 'User',
                email: $user->email,
                date_of_birth: null,
                civility: null,
                profession: null,
                password: null,
                role: null,
            );

            $this->repository->update($user, $userDto);
            $user->refresh();

            expect($user->password)->toBe($originalPasswordHash);
        });
    });

    describe('delete', function () {
        it('deletes a user and removes roles', function () {
            Event::fake([UserDeleted::class]);

            $user = User::factory()->create();
            $user->assignRole(UserRoleEnum::CONTRIBUTOR->value);
            $userId = $user->id;

            $this->repository->delete($user);

            expect(User::find($userId))->toBeNull();
            Event::assertDispatched(UserDeleted::class);
        });
    });

    describe('find', function () {
        it('finds a user by id', function () {
            $user = User::factory()->create();

            $foundUser = $this->repository->find($user->id);

            expect($foundUser)->toBeInstanceOf(User::class)
                ->and($foundUser->id)->toBe($user->id);
        });

        it('returns null for non-existent user', function () {
            $foundUser = $this->repository->find(99999);

            expect($foundUser)->toBeNull();
        });
    });

    describe('findOrFail', function () {
        it('finds a user by id', function () {
            $user = User::factory()->create();

            $foundUser = $this->repository->findOrFail($user->id);

            expect($foundUser->id)->toBe($user->id);
        });

        it('throws exception for non-existent user', function () {
            $this->repository->findOrFail(99999);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
