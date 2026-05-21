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
php artisan arkhe:main:install
```

The interactive installer walks through every step in order:

1. Publish `config/arkhe.php`.
2. Publish the migration that adds profile columns (`first_name`, `last_name`, `avatar_path`, `phone`, `date_of_birth`, `civility`, `bio`) to the `users` table.
3. If `spatie/laravel-permission` is not migrated yet, publish its config and migrations automatically — no need to run its setup separately.
4. Optionally publish the views.
5. Run `php artisan migrate`.
6. Seed the four default roles: `root`, `administrateur`, `user`, `guest`.
7. Offer to add the `HasBackendProfile` trait to your `App\Models\User` automatically (skipped if the model already uses `HasRoles`, which would conflict).
8. Create a first **root** user via interactive prompts.

## Creating users from the CLI

Once installed, add new users without leaving the terminal:

```bash
# fully interactive
php artisan arkhe:main:add-user

# one-liner with explicit options
php artisan arkhe:main:add-user \
    --email=ops@example.com \
    --first=Ops \
    --last=Team \
    --role=administrateur \
    --password=...
```

The command lets you pick the role from a prompt populated by the `roles` table. CLI calls bypass the runtime role-hierarchy check (the assumption being that anyone with shell access already has full authority), so you can seed a `root` user from a deploy script without auth context.

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
use Arkhe\Main\Concerns\HasBackendProfile;

class User extends Authenticatable
{
    use HasBackendProfile; // ⚠ already pulls in Spatie's HasRoles — do NOT add `use HasRoles;` separately.
}
```

The trait adds three accessors (`full_name`, `avatar_url`, `initials`) and two helpers (`isArkheRoot()`, `isArkheAdmin()`).

## Accessing the backend

By default: `GET /administration/users` (the prefix is configurable).

Access is granted to users carrying either the `root` or `administrateur` role; everyone else gets a `403` via the `arkhe.backend` middleware.

## Coexisting with a custom admin (Livewire starter kit)

Arkhe is designed to **plug into** your existing admin shell rather than replace it. Two integration points:

### 1. Use your app's layout

In `config/arkhe.php`:

```php
'layout' => 'components.layouts.app', // your starter kit layout
```

Arkhe pages will render inside your existing chrome (sidebar, topbar, your CSS).

### 2. Take over the dashboard route (opt-in)

Comment out the dashboard route in your `routes/web.php` and set the path:

```dotenv
ARKHE_DASHBOARD_ROUTE=administration/dashboard
```

Arkhe mounts a minimal users-by-role dashboard at the path you choose. Keep the env var unset and your own dashboard remains untouched.

**Login redirect.** The Laravel starter kits redirect to `route('dashboard', absolute: false)` after authentication. Two ways to point that at Arkhe:

- **A — re-use the `dashboard` route name** (zero patch on your starter):

  ```dotenv
  ARKHE_DASHBOARD_ROUTE=administration/dashboard
  ARKHE_DASHBOARD_ROUTE_NAME=dashboard
  ```

  `route('dashboard')` now resolves to `/administration/dashboard`, the after-login redirect just works.

- **B — keep `arkhe.dashboard` and patch the starter's login**: open the login Livewire/Volt component and replace `route('dashboard', absolute: false)` with `route('arkhe.dashboard', absolute: false)`. Pick this when another part of your app still needs `dashboard` to mean something else.

> **Fortify users** (Laravel 12 Livewire starter kit included): the starter's login form posts to Fortify, which redirects to the literal value of `config('fortify.home')` after auth — not via the named `dashboard` route. Arkhe detects Fortify automatically and rewrites that value to your `ARKHE_DASHBOARD_ROUTE` at boot, so neither A nor B is needed for the form submission to land on the right page. Set `ARKHE_OVERRIDE_FORTIFY_REDIRECT=false` to opt out.

### 3. Inject Arkhe entries into your sidebar

Include the bundled partial inside one of your `<flux:sidebar.group>` blocks (the partial emits plain `<flux:sidebar.item>` entries, no wrapper — you decide the group and the order):

```blade
<flux:sidebar.nav>
    <flux:sidebar.group :heading="__('Platform')" class="grid">
        {{-- your custom admin links --}}
        <flux:sidebar.item icon="folder" :href="route('admin.projects.index')" wire:navigate>
            Projects
        </flux:sidebar.item>

        {{-- Arkhe entries (Dashboard if enabled, Users) --}}
        @include('arkhe::partials.sidebar-items')
    </flux:sidebar.group>
</flux:sidebar.nav>
```

You can also publish the partial to customise it:

```bash
php artisan vendor:publish --tag=arkhe-views
# then edit resources/views/vendor/arkhe/partials/sidebar-items.blade.php
```

## Role hierarchy & authorization

The `Arkhe\Main\Support\RoleHierarchy` helper encodes a configurable role hierarchy. A user can only assign roles whose rank is less than or equal to their own. The default order, highest first, is:

```
root > administrateur > user > guest
```

This means: only `root` can assign `root`; an `administrateur` cannot promote anyone to `root`; and so on.

The hierarchy is enforced at three layers:

- the role `<select>` only lists roles the acting user can assign;
- a closure rule on `UserForm` rejects any out-of-rank role at validation time;
- `UserService::syncRolesAndPermissions()` throws `AuthorizationException` as a backstop for direct service callers.

### Extending the hierarchy

Two extension paths are available — pick the one that matches your situation.

#### Option A — Static, via `config/arkhe.php`

Use this when the roles are known at deploy time and live with the application: the values you want are part of the codebase, not contributed by an external package.

The **order** of `config('arkhe.roles')` IS the hierarchy (first entry = highest rank). Insert your role at the right position between two existing entries:

```php
// config/arkhe.php
'roles' => [
    'root'          => 'root',
    'administrator' => 'administrateur',
    'manager'       => 'manager',   // new role, ranks between admin and user
    'user'          => 'user',
    'guest'         => 'guest',
],
```

Then create the matching row in the `roles` table by re-running the bundled seeder:

```bash
php artisan arkhe:main:install   # answer No to publish + migrate, Yes is automatic on the seed step
# or, equivalently in a one-liner:
php artisan tinker --execute="app(\Arkhe\Main\Database\Seeders\ArkheRolesSeeder::class)->run();"
```

Pros: declarative, version-controlled, visible to every dev reading the config. Cons: requires editing the published file in each host app.

#### Option B — Runtime, via `RoleHierarchy::register()`

Use this when the role is contributed by a **package, module or feature flag** — i.e. you cannot (or do not want to) require the host app to edit `config/arkhe.php`.

From your package's service provider:

```php
use Arkhe\Main\Support\RoleHierarchy;

public function boot(): void
{
    RoleHierarchy::register('manager', after: 'administrateur');
    RoleHierarchy::register('editor',  before: 'user');
    RoleHierarchy::register('intern');                 // append at the lowest rank
}
```

`register()` can also reposition an already-known role on subsequent calls. Your package is still responsible for creating the matching row in the `roles` table (typically via its own seeder).

Pros: zero config edit on the host side, perfect for distributed packages. Cons: invisible at first glance — document loudly which role(s) your package contributes.

#### Which one to pick?

| Scenario | Recommended |
|---|---|
| You own the app code end-to-end and the role belongs there | **A — config** |
| The role ships in a Composer/Git submodule that you `require` from many apps | **B — register()** |
| You want a flag to enable/disable a role per environment | **B — register()** inside an `if (config('feature.x'))` |

#### Contract

The four canonical Arkhe keys — `root`, `administrator`, `user`, `guest` — must remain in `config('arkhe.roles')`. Internal Arkhe code references them directly (`config('arkhe.roles.root')`, …). You can:

- ✅ insert new roles between them,
- ✅ change the **value** (the actual role name stored in DB), e.g. `'user' => 'membre'`,
- ❌ rename or remove the four canonical **keys**.

## Styling — Tailwind / Flux

Tailwind only compiles classes it can **see**. Since this package's Blade files live in `vendor/adhocrat-io/arkhe-main/resources/views/`, they are not scanned by the default Laravel setup. Add the package's view path to your Tailwind source list, then rebuild your assets.

**Tailwind 4 (recommended, used by Flux 2):**

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

@source '../views';
@source '../../app/Livewire';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../vendor/adhocrat-io/arkhe-main/resources/views';
```

**Tailwind 3:**

```js
// tailwind.config.js
export default {
  content: [
    './resources/views/**/*.blade.php',
    './app/Livewire/**/*.php',
    './vendor/livewire/flux/stubs/**/*.blade.php',
    './vendor/adhocrat-io/arkhe-main/resources/views/**/*.blade.php',
  ],
  // ...
}
```

Then:

```bash
pnpm build   # or npm run build / yarn build
```

The layout published with the package uses `@fluxAppearance` and `@fluxScripts` from `livewire/flux` — make sure those directives are reachable (they ship with Flux automatically).

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
| `Arkhe\Main\Events\UserCreated` | `UserService::create()` | The fresh `Model` |
| `Arkhe\Main\Events\UserUpdated` | `UserService::update()` | The fresh `Model` |
| `Arkhe\Main\Events\UserDeleted` | `UserService::delete()` | The deleted `Model` |

## Translations

Locale by default: `fr` (with `en` fallback). Override per-app via:

```bash
php artisan vendor:publish --tag=arkhe-translations
```

## Phase 2 (preview)

The package exposes two boolean feature flags wired through `Arkhe\Main\Support\Features`:

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
