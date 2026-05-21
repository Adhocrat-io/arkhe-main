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
| ralphjsmit/laravel-seo | `^1.8` |
| spatie/laravel-sitemap | `^8.1` |

## Installation

```bash
composer require adhocrat-io/arkhe-main
php artisan arkhe:main:install
```

The interactive installer walks through every step in order:

1. Publish `config/arkhe.php`.
2. Publish the migration that adds profile columns (`first_name`, `last_name`, `avatar_path`, `phone`, `date_of_birth`, `civility`, `bio`) to the `users` table.
3. If `spatie/laravel-permission` is not migrated yet, publish its config and migrations automatically — no need to run its setup separately.
4. If `ralphjsmit/laravel-seo` is not migrated yet, publish its migration + config (see [SEO](#seo)).
5. Optionally publish the views.
6. Run `php artisan migrate`.
7. Seed the four default roles: `root`, `administrateur`, `user`, `guest`.
8. Patch your `<flux:sidebar.nav>` with `@include('arkhe::partials.sidebar-items')` (idempotent — skipped if already done).
9. Patch your Tailwind v4 `resources/css/app.css` with the `@source` directive needed to scan the package's Blade views (idempotent). For Tailwind v3 setups, the installer prints the equivalent `content` glob to add to `tailwind.config.js`.
10. Offer to add the `HasBackendProfile` trait to your `App\Models\User` automatically (skipped if the model already uses `HasRoles`, which would conflict).
11. Create a first **root** user via interactive prompts.

Every step is **idempotent** — re-running `arkhe:main:install` after an upgrade is safe and is the recommended way to pick up new install-time integrations (see [Upgrading](#upgrading)).

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

Tailwind only compiles classes it can **see**. Since this package's Blade files live in `vendor/adhocrat-io/arkhe-main/resources/views/`, they are not scanned by the default Laravel setup. The installer takes care of this for Tailwind v4 (step 8 above); the snippets below document the same thing for reference or for manual setups.

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

## SEO

`adhocrat-io/arkhe-main` ships [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) as a first-class dependency since 3.1.0. The `arkhe:main:install` command publishes the SEO package's migration and config, and the package's `seo()` helper is rendered in the Arkhe layout's `<head>`.

### Site-wide defaults — `/administration/seo`

A root-only Livewire page at `/administration/seo` (named `arkhe.site-seo.edit`) edits the site SEO defaults stored in the `arkhe_site_seo` table:

- Site name (used in OpenGraph tags)
- Title suffix (appended to every page `<title>`, e.g. `| Acme`)
- Default description
- Default OG image
- Author
- Robots
- Twitter / X handle
- Favicon

These values are merged into the `SEOData` rendered on every page via a `SEOManager::SEODataTransformer` registered by `Arkhe\Main\ArkheMainServiceProvider::bootSeo()`. They serve as fallbacks: anything provided per-page or per-model (see below) wins.

### Per-model SEO — `HasArkheSeo` trait

Drop the trait on any Eloquent model to get per-record SEO storage:

```php
use Arkhe\Main\Concerns\HasArkheSeo;

class Post extends Model
{
    use HasArkheSeo;
}
```

This creates a polymorphic `seo` row on every new `Post`, exposes a `$post->seo` relation, and lets you render:

```blade
{!! seo($post) !!}
```

You can also override SEO dynamically by implementing `getDynamicSEOData()` on your model (see the [upstream package docs](https://github.com/ralphjsmit/laravel-seo) for the full API).

The merge order (highest priority first):

1. `getDynamicSEOData()` overrides on the resolved model
2. The polymorphic `seo` row (`$model->seo`)
3. Arkhe site defaults from `/administration/seo`
4. `config('seo.php')` (the upstream package's static defaults)

### Disabling the integration

Set `arkhe.features.seo` to `false` in your `config/arkhe.php` to skip the SEOData transformer registration. The `seo()` helper still works (the upstream package is always loaded), it just won't pick up Arkhe's site defaults.

## Sitemap

Arkhe ships [`spatie/laravel-sitemap`](https://github.com/spatie/laravel-sitemap) since 3.1.0. The package registers a scheduled `GenerateSitemap` job and exposes a root-only admin page at `/administration/sitemap` (route `arkhe.sitemap.edit`) to inspect status and trigger a regeneration on demand.

### Configuration

`config/arkhe.php`:

```php
'sitemap' => [
    'enabled'  => env('ARKHE_SITEMAP_ENABLED', true),
    'url'      => env('ARKHE_SITEMAP_URL'),      // null → falls back to config('app.url')
    'path'     => env('ARKHE_SITEMAP_PATH'),     // null → falls back to public_path('sitemap.xml')
    'schedule' => env('ARKHE_SITEMAP_SCHEDULE', '0 3 * * *'),
],
```

The cron expression is registered with `callAfterResolving(Schedule::class, …)` so it lights up as soon as the host app's scheduler runs. To skip the automatic scheduling without losing the admin button, set `ARKHE_SITEMAP_ENABLED=false`.

### Running the job manually

```bash
php artisan queue:work          # if the queue isn't already running
# then click "Regenerate now" on /administration/sitemap
```

The `Regenerate now` button dispatches `Arkhe\Main\Jobs\GenerateSitemap` onto the host app's default queue. With the `sync` driver, the regeneration runs inline — same code path, no scheduler dependency.

### Customising the generator

Subclass `Arkhe\Main\Services\SitemapService` and override `configureGenerator(SitemapGenerator $generator): void` to add URLs, swap the crawl profile, or filter pages. Then point the binding at your subclass:

```php
// AppServiceProvider::register
$this->app->bind(\Arkhe\Main\Services\SitemapService::class, \App\Services\MySitemapService::class);
```

For per-model integration, implement Spatie's `Sitemapable` contract on any Eloquent model — see the [upstream docs](https://github.com/spatie/laravel-sitemap).

## Phase 2 (preview)

The package exposes a feature flag wired through `Arkhe\Main\Support\Features`:

```php
Features::hasCookieConsent(); // false until phase 2
Features::hasSeo();           // true since 3.1.0
```

Toggle them via `config/arkhe.php`. SEO is on by default; cookie consent flips on when the sub-package lands.

## Upgrading

### Between minor / patch versions

```bash
composer update adhocrat-io/arkhe-main
php artisan arkhe:main:install   # re-run, answer "no" to steps already done
```

`arkhe:main:install` is idempotent on every step (publish, migrate, seed, sidebar patch, css patch, trait patch). Re-running it after upgrading is the canonical way to pick up new install-time integrations (e.g. a new `@source` to add to `app.css`, a new sidebar entry to inject).

If you'd rather skip the prompts, the manual snippets in the [Styling](#styling--tailwind--flux) and [Wiring up your User model](#wiring-up-your-user-model) sections give you the exact lines to add.

### From V2 to V3

V3 keeps the V2 public surface — namespace `Arkhe\Main`, service provider, config prefix — so no global search-replace is required. A dedicated Artisan command handles the config migration:

```bash
composer update adhocrat-io/arkhe-main:^3.0
php artisan arkhe:main:upgrade-from-v2 --dry-run   # preview the changes
php artisan arkhe:main:upgrade-from-v2             # apply
```

What it does:

- Appends V3-only keys to your published `config/arkhe.php` (`dashboard_route`, `role_permissions`, `components`, `backend_permission`, `root_permission`, `features`) without touching existing V2 entries.
- Rewrites legacy Livewire aliases inside `resources/views/` (e.g. `arkhe.main.livewire.admin.users.users-list` → `arkhe.list-users`).
- Runs the V3 permission seeder so the new 16 default permissions and their role mappings land in your DB.

See [`CHANGELOG.md`](CHANGELOG.md) for the full breaking-change list and the V2 → V3 migration playbook.

## Testing

```bash
composer install
vendor/bin/pest
```

CI runs the matrix PHP `8.3`/`8.4` × Laravel `12.*`/`13.*` × `prefer-lowest`/`prefer-stable`.

## License

[MIT](LICENSE) — Luc, adhocrat.io.
