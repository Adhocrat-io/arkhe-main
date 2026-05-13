# adhocrat-io/arkhe-main

Bootstrap a Laravel backend with **users, roles and permissions** management, served by **Livewire 4** and **Flux UI Free**.

> Phase 1 of the `adhocrat-io/arkhe-*` namespace.

## Requirements

| Component | Version |
| --- | --- |
| PHP | `^8.3` |
| Laravel | `^12.0 || ^13.0` |
| Livewire | `^4.0` |
| Flux UI | `^2.1` (Free edition) |
| Spatie laravel-permission | `^7.0` |

## Installation

```bash
composer require adhocrat-io/arkhe-main
```

Run Spatie's permission setup if you haven't already (the package depends on it):

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Then run the interactive installer:

```bash
php artisan arkhe:main:install
```

The installer will:

1. Publish `config/arkhe.php`.
2. Publish the migration that adds the profile columns (`first_name`, `last_name`, `avatar_path`, `phone`, `date_of_birth`, `civility`, `bio`) to the `users` table.
3. Optionally publish the views.
4. Run `php artisan migrate`.
5. Seed the four default roles: `root`, `administrateur`, `user`, `guest`.
6. Create a first **root** user (interactive prompts).

## Configuration

`.env`:

```dotenv
ARKHE_ROUTE_PREFIX=administration
ARKHE_AVATAR_DISK=public
ARKHE_AVATAR_PATH=avatars
```

The full configuration lives in `config/arkhe.php` after publishing.

## Wiring up your User model

Add the `HasBackendProfile` trait to your `User` model.

```php
use Adhocrat\Arkhe\Concerns\HasBackendProfile;

class User extends Authenticatable
{
    use HasBackendProfile; // ⚠ already pulls in Spatie's HasRoles — do NOT add `use HasRoles;` separately.
}
```

The trait adds three accessors (`full_name`, `avatar_url`, `initials`) and two helpers (`isArkheRoot()`, `isArkheAdmin()`).

## Accessing the backend

By default: `GET /administration/users` (the prefix is configurable).

Access is granted to users carrying either the `root` or `administrateur` role; everyone else gets a `403` via the `arkhe.backend` middleware.

## Architecture

The package strictly follows the **Repository + Service** pattern:

```
Livewire Component
   ├──[read]──▶ UserRepository ──▶ Eloquent
   └──[mutate]─▶ UserService ──▶ UserRepository ──▶ Eloquent
                       └──▶ Events (UserCreated / UserUpdated / UserDeleted)
```

No Eloquent query happens outside `src/Repositories/`. No mutation happens outside `src/Services/`. When extending, follow the same rule — see `CONTRIBUTING` for details.

### Events

| Event | Dispatched by | Payload |
| --- | --- | --- |
| `Adhocrat\Arkhe\Events\UserCreated` | `UserService::create()` | The fresh `Model` |
| `Adhocrat\Arkhe\Events\UserUpdated` | `UserService::update()` | The fresh `Model` |
| `Adhocrat\Arkhe\Events\UserDeleted` | `UserService::delete()` | The deleted `Model` |

## Translations

Locale by default: `fr` (with `en` fallback). Override per-app via:

```bash
php artisan vendor:publish --tag=arkhe-translations
```

## Phase 2 (preview)

The package exposes two boolean feature flags wired through `Adhocrat\Arkhe\Support\Features`:

```php
Features::hasCookieConsent(); // false until phase 2
Features::hasSeo();           // false until phase 2
```

Toggle them via `config/arkhe.php` once their respective sub-packages land.

## Testing

```bash
composer install
vendor/bin/pest
```

CI runs the matrix PHP `8.3`/`8.4` × Laravel `12.*`/`13.*` × `prefer-lowest`/`prefer-stable`.

## License

[MIT](LICENSE) — Luc, adhocrat.io.
