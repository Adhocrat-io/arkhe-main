<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use App\Models\User;
use Arkhe\Main\DataTransferObjects\UserDto;
use Arkhe\Main\Events\UserCreated;
use Arkhe\Main\Events\UserDeleted;
use Arkhe\Main\Events\UserUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    private function getQuery(): Builder
    {
        return User::query()->with(['roles']);
    }

    public function create(UserDto $userDto): User
    {
        return DB::transaction(function () use ($userDto) {
            $user = User::create($userDto->toArray());

            if ($userDto->role) {
                $user->assignRole($userDto->role);
            }

            UserCreated::dispatch($user, Auth::user());

            return $user;
        });
    }

    public function update(User $user, UserDto $userDto): User
    {
        return DB::transaction(function () use ($user, $userDto) {
            $data = $userDto->toArray();

            // Don't update password if empty
            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);

            // Update role if provided
            if ($userDto->role) {
                $user->syncRoles([$userDto->role]);
            }

            UserUpdated::dispatch($user, Auth::user());

            return $user->refresh();
        });
    }

    public function find(int $userId): ?User
    {
        return $this->getQuery()->find($userId);
    }

    public function findOrFail(int $userId): User
    {
        return $this->getQuery()->findOrFail($userId);
    }

    public function getAllUsers(): Builder
    {
        return $this->getQuery();
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $deletedBy = Auth::user();
            $user->syncRoles([]);
            $user->delete();

            UserDeleted::dispatch($user, $deletedBy);
        });
    }
}
