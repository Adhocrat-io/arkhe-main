<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Arkhe\Main\Events\UserCreated;
use Arkhe\Main\Events\UserDeleted;
use Arkhe\Main\Events\UserUpdated;
use Arkhe\Main\Support\RoleHierarchy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Contracts\Filesystem\Factory as StorageFactory;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class UserService
{
    /**
     * @var array<int, string>
     */
    private const PROFILE_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'civility',
        'bio',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly Hasher $hasher,
        private readonly StorageFactory $storage,
        private readonly ConfigRepository $config,
        private readonly EventsDispatcher $events,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $user = $this->repository->newModel();

        $this->fill($user, $data);

        if (! empty($data['password'])) {
            $user->password = $this->hasher->make((string) $data['password']);
        }

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $user->avatar_path = $this->storeAvatar($data['avatar']);
        }

        $user->save();

        $this->syncRolesAndPermissions($user, $data);

        $this->events->dispatch(new UserCreated($user));

        return $user->fresh() ?? $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $user, array $data): Model
    {
        $this->assertCanManage($user);

        $this->fill($user, $data);

        if (! empty($data['password'])) {
            $user->password = $this->hasher->make((string) $data['password']);
        }

        // A new file wins over a requested removal: we do not delete what we
        // have just replaced.
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->deleteAvatar($user->avatar_path ?? null);
            $user->avatar_path = $this->storeAvatar($data['avatar']);
        } elseif (! empty($data['removeAvatar'])) {
            $this->deleteAvatar($user->avatar_path ?? null);
            $user->avatar_path = null;
        }

        $user->save();

        $this->syncRolesAndPermissions($user, $data);

        $this->events->dispatch(new UserUpdated($user));

        return $user->fresh() ?? $user;
    }

    public function delete(Model $user): void
    {
        $this->assertCanManage($user);

        $this->deleteAvatar($user->avatar_path ?? null);

        $user->delete();

        $this->events->dispatch(new UserDeleted($user));
    }

    /**
     * Refuse the operation when the actor outranks the target.
     * CLI / artisan / queued jobs (no auth context) are allowed through —
     * those callers already require shell access.
     *
     * @throws AuthorizationException
     */
    private function assertCanManage(Model $target): void
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        if ($actor === null && app()->runningInConsole()) {
            return;
        }

        if (! RoleHierarchy::canManage($actor, $target)) {
            throw new AuthorizationException(
                'You are not allowed to manage a user with a higher role than your own.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Model $user, array $data): void
    {
        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field] === '' ? null : $data[$field];
            }
        }

        // Some host apps keep a NOT NULL `name` column on `users` from
        // the Laravel starter kits. Mirror it from first_name/last_name
        // if it exists, so we don't break the insert.
        if (Schema::hasColumn($user->getTable(), 'name')) {
            $first = (string) ($data['first_name'] ?? $user->first_name ?? '');
            $last = (string) ($data['last_name'] ?? $user->last_name ?? '');
            $user->name = trim($first.' '.$last);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRolesAndPermissions(Model $user, array $data): void
    {
        if (array_key_exists('roles', $data) && is_array($data['roles'])) {
            $this->assertCanAssignRoles($data['roles']);
            $user->syncRoles($data['roles']);
        }

        if (array_key_exists('permissions', $data) && is_array($data['permissions'])) {
            $this->assertCanGrantPermissions($user, $data['permissions']);
            $user->syncPermissions($data['permissions']);
        }
    }

    /**
     * You only grant a permission you hold yourself.
     *
     * Rank already protected the roles, but nothing guarded permissions
     * assigned directly: `create-user` was enough to build an account carrying
     * `manage-roles`, then log into it. The hierarchy was bypassed through the
     * door next to it.
     *
     * Only the delta is checked: removing a permission you do not hold stays
     * allowed, and re-saving an account without touching it asks for nothing.
     *
     * @param  array<int, string>  $permissions
     *
     * @throws AuthorizationException
     */
    private function assertCanGrantPermissions(Model $user, array $permissions): void
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        if ($actor === null) {
            if (app()->runningInConsole()) {
                return;
            }

            throw new AuthorizationException('Cannot change permissions without an authenticated actor.');
        }

        if (method_exists($actor, 'hasRole') && $actor->hasRole((string) $this->config->get('arkhe.roles.root'))) {
            return;
        }

        $current = method_exists($user, 'getDirectPermissions')
            ? $user->getDirectPermissions()->pluck('name')->all()
            : [];

        foreach (array_diff($permissions, $current) as $permission) {
            if (! method_exists($actor, 'can') || ! $actor->can($permission)) {
                throw new AuthorizationException(
                    "You are not allowed to grant the permission [{$permission}]."
                );
            }
        }
    }

    /**
     * @param  array<int, string>  $roles
     *
     * @throws AuthorizationException
     */
    private function assertCanAssignRoles(array $roles): void
    {
        /** @var Model|null $actor */
        $actor = $this->auth->guard()->user();

        // CLI / artisan / queued jobs have no auth context. Skip the rank
        // check there — those callers already require shell access to run.
        if ($actor === null && app()->runningInConsole()) {
            return;
        }

        foreach ($roles as $role) {
            if (! RoleHierarchy::canAssign($actor, (string) $role)) {
                throw new AuthorizationException(
                    "You are not allowed to assign the role [{$role}]."
                );
            }
        }
    }

    private function storeAvatar(UploadedFile $file): string
    {
        $disk = (string) $this->config->get('arkhe.avatar_disk', 'public');
        $path = (string) $this->config->get('arkhe.avatar_path', 'avatars');

        $stored = $this->storage->disk($disk)->putFile($path, $file);

        return is_string($stored) ? $stored : '';
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = (string) $this->config->get('arkhe.avatar_disk', 'public');
        $this->storage->disk($disk)->delete($path);
    }
}
