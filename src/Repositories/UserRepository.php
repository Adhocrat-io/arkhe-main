<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use App\Models\User;
use Arkhe\Main\DataTransferObjects\UserDto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class UserRepository
{
    private function getQuery(): Builder
    {
        return User::query()->with(['roles']);
    }

    public function create(UserDto $userDto): User
    {
        if (auth()->check() && ! auth()->user()->can('create-user')) {
            throw new AuthorizationException(__('You do not have the permissions to create a user.'));
        }

        $user = User::create($userDto->toArray());
        $user->assignRole($userDto->role);

        // Action ?

        return $user;
    }

    public function update(User $user, UserDto $userDto): User
    {
        if (auth()->check() && ! auth()->user()->can('update-user')) {
            throw new AuthorizationException(__('You do not have the permissions to update a user.'));
        }

        $data = $userDto->toArray();

        // Ne pas mettre à jour le mot de passe s'il est vide
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // Mettre à jour le rôle si fourni
        if ($userDto->role) {
            $user->syncRoles([$userDto->role]);
        }

        return $user->refresh();
    }

    public function find(int $userId): User
    {
        return User::find($userId);
    }

    public function getAllUsers(): Builder
    {
        return $this->getQuery();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
