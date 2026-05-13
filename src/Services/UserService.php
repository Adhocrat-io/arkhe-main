<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Services;

use Adhocrat\Arkhe\Contracts\UserRepositoryInterface;
use Adhocrat\Arkhe\Events\UserCreated;
use Adhocrat\Arkhe\Events\UserDeleted;
use Adhocrat\Arkhe\Events\UserUpdated;
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
    ) {
    }

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
        $this->fill($user, $data);

        if (! empty($data['password'])) {
            $user->password = $this->hasher->make((string) $data['password']);
        }

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->deleteAvatar($user->avatar_path ?? null);
            $user->avatar_path = $this->storeAvatar($data['avatar']);
        }

        $user->save();

        $this->syncRolesAndPermissions($user, $data);

        $this->events->dispatch(new UserUpdated($user));

        return $user->fresh() ?? $user;
    }

    public function delete(Model $user): void
    {
        $this->deleteAvatar($user->avatar_path ?? null);

        $user->delete();

        $this->events->dispatch(new UserDeleted($user));
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
            $last  = (string) ($data['last_name']  ?? $user->last_name  ?? '');
            $user->name = trim($first.' '.$last);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRolesAndPermissions(Model $user, array $data): void
    {
        if (array_key_exists('roles', $data) && is_array($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (array_key_exists('permissions', $data) && is_array($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
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
