# adhocrat-io/arkhe-main

*Read this in [French / Français](README.fr.md).*

Bootstrap a Laravel backend with **users, roles and permissions** management, served by **Livewire 4** and **Flux UI Free**. Ships first-class SEO, sitemap and cookie-consent integrations on top.

> First module of the `adhocrat-io/arkhe-*` namespace.

## Requirements

| Component | Version |
| --- | --- |
| PHP | `^8.3` |
| Laravel | `^12.0 || ^13.0` |
| Livewire | `^4.2` |
| Flux UI | `^2.1` (Free edition) |
| Spatie laravel-permission | `^7.0` |
| ralphjsmit/laravel-seo | `^1.8` |
| spatie/laravel-sitemap | `^8.1` |
| whitecube/laravel-cookie-consent | `^1.3` |

### Recommended starting point

The installer expects a Livewire 4 + Flux UI host app. The smoothest experience is on **Laravel 12 with the Livewire/Volt starter kit** (or the Flux starter), because:

- the layout default `layouts::app` (the Livewire 4 convention) resolves to a view the starter kit ships;
- the sidebar partial injection (step 8 below) targets the starter's `<flux:sidebar.nav>`;
- the Tailwind v4 `@source` patch (step 9 below) targets the starter's `resources/css/app.css`.

On a **bare Laravel app with no starter kit** the package still installs cleanly — the optional steps (sidebar + CSS) are skipped silently. You then have to point Arkhe at its own bundled layout (header-only, no sidebar):

```dotenv
ARKHE_ADMIN_LAYOUT=arkhe::layouts.app
```

See [Limitations](#limitations) for the full list of skip conditions.

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

## Strong authentication

Arkhe can require a strong second factor — a registered **passkey** or a **confirmed TOTP** — before anyone reaches the backend. A passkey exempts from TOTP: it is already two-factor (device possession plus biometric or PIN) and, being bound to the domain by the browser, phishing-resistant where a TOTP code is not.

This gates **access to the backend, not sign-in**. How users authenticate belongs to your app's Fortify pipeline, which the package does not touch — a user without a factor stays signed in and keeps the rest of the site; only `/administration/*` closes until they enrol. Fortify offers no equivalent: it provides two-factor authentication but never compels it, and ships no middleware at all.

### Turning it on

Disabled by default, so upgrading the package never locks an app out of its own backend. Four steps, in this order — the order matters, and step 3 is the one people skip.

**1. Check your app can satisfy it.** Enforcement needs a user model exposing at least one of the two probe methods below. With Fortify's `TwoFactorAuthenticatable` and/or `laravel/passkeys` on your `User`, you are set. Without either, the flag has no effect (see [When it cannot be satisfied](#when-it-cannot-be-satisfied)).

```bash
php artisan tinker --execute="\$u = App\Models\User::first();
  var_dump(method_exists(\$u, 'hasEnabledTwoFactorAuthentication'));
  var_dump(method_exists(\$u, 'hasPasskeysEnabled'));"
```

**2. Extend the gate to your own admin pages.** Arkhe guards its own routes; your dashboard is yours. See [Extending it to your own routes](#extending-it-to-your-own-routes) — the alias is inert until step 4, so this is safe to do first.

**3. Enrol yourself before switching it on.** Go to your security settings and register a passkey (or confirm a TOTP) *now*, while the backend is still open. Skipping this is not fatal — the interstitial always routes you to the enrolment page — but doing it first spares you the detour on every attempt.

**4. Switch it on.**

```dotenv
ARKHE_STRONG_AUTH=true
```

```bash
php artisan config:clear
```

Verify it took effect — this must print `true`:

```bash
php artisan tinker --execute="var_dump(Arkhe\Main\Support\StrongAuth::enabled());"
```

It is all or nothing: either the backend requires a strong factor or it does not. Guarding only the sensitive area was considered and dropped — it left the user list open, where accounts are created and roles handed out, which reads as prudence while buying little. Roles and permissions already draw that line where it belongs.

Anything unrecognised reads as disabled rather than as "protect everything", so a typo fails towards a working backend rather than towards a lockout.

The published config, should you want to set it there instead of in `.env`:

```php
// config/arkhe.php
'strong_auth' => [
    'enforce' => env('ARKHE_STRONG_AUTH', false),
    'route'   => null,   // enrolment page; auto-detected when null
],
```

> **Config published before this release?** The key will simply be absent, which reads as `false`. The middleware itself is wired into the package's own routes rather than into `arkhe.middleware`, so it reaches you even with a frozen published stack.

### What counts as a strong factor

Detection probes the user model for two methods, never for traits or vendor classes, so neither `laravel/fortify` nor `laravel/passkeys` becomes a dependency:

| Method | Comes from | Cost |
| --- | --- | --- |
| `hasEnabledTwoFactorAuthentication()` | Fortify's `TwoFactorAuthenticatable` | attribute read, no query |
| `hasPasskeysEnabled()` | `laravel/passkeys` | one `exists()` query |

Either one satisfies the requirement. Two-factor is probed first because it is free, so the passkey query only runs for users who have no TOTP. The verdict is deliberately not cached: a revoked factor must stop working immediately, and under Octane a memoized verdict would outlive the request that produced it. A TOTP secret that was generated but never confirmed does **not** count.

### When it cannot be satisfied

Two degraded states are handled rather than left to fail:

- **No enrolment page resolves.** Arkhe probes `security.edit` then `two-factor.show`. Profile pages are deliberately excluded — in current starter kits they carry no 2FA or passkey controls, so sending someone there would strand them where nothing can be enabled. When nothing resolves, the interstitial drops its call to action and reports which config key to set, rather than linking into the void.
- **The user model exposes neither method.** No user could ever satisfy the requirement, so it is skipped with a logged warning. Failing closed would brick the backend with no way back in, since the config is only reachable with server access — and this state means the flag was set on an app with no 2FA support, which is a misconfiguration rather than an intent.

### Extending it to your own routes

Arkhe guards its own pages. Your dashboard, and anything else you consider part of the admin area, belongs to your app — so the gate is exposed as a reusable middleware alias you apply where you want it:

```php
// routes/web.php
Route::middleware(['auth', 'verified', 'arkhe.strong-auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
```

The dashboard is worth protecting: it is the way into the admin area, and leaving it open only postpones the block to the first click. The alias is inert while `arkhe.strong_auth.enforce` is `false`, so adding it costs nothing until you switch enforcement on.

Place it after `auth`, which resolves the user it reads. There is nothing else to configure — the alias takes no parameter.

### Why route middleware alone is not enough

Livewire's update endpoint is a separate route carrying only `['web']`, so every action after the initial page render — saving a user, deleting a role — travels a path where route middleware does not run. A gate wired only on `/administration/*` would guard the first GET and nothing else: anyone holding a snapshot from an earlier legitimate page load could keep acting on the backend.

Arkhe closes that on both halves. The three gates are declared persistent (`Livewire::addPersistentMiddleware()`), so Livewire re-applies them on component requests; and backend components carry `RequiresStrongAuth`, which re-asserts the requirement server-side on every request through Livewire's `booted()` hook. The second half matters because persistent middleware still keys off the client-supplied snapshot path — a gate whose only enforcement can be influenced by the payload it polices is not a gate.

If you build your own backend Livewire components, apply the trait to them too:

```php
use Arkhe\Main\Concerns\RequiresStrongAuth;

class MyAdminPage extends Component
{
    use RequiresStrongAuth;
}
```

### What a blocked user sees

They land on an Arkhe page at `/administration/strong-auth` that names the requirement, describes both options, and walks through what comes next — including the password prompt, which most starter kits put in front of their security page. Only then does it hand off.

The interstitial exists because handing off directly did not work: the security page belongs to your app and usually sits behind `password.confirm`, and that intermediate hop consumes any flashed message. Users met a password prompt and a settings screen with nothing saying what was expected of them.

The page sits **outside** the guarded route group, since a gate that redirects to a page it also guards is an infinite loop. It stays registered even when enforcement is off, so a stale link finds a page rather than a 404. Override it like any other Arkhe page:

```php
'components' => [
    'strong-auth-required' => App\Livewire\MyStrongAuthNotice::class,
],
```

## Coexisting with a custom admin (Livewire starter kit)

Arkhe is designed to **plug into** your existing admin shell rather than replace it. Two integration points:

### 1. Use your app's layout

In `config/arkhe.php`:

```php
'layout' => 'components.layouts.app', // your starter kit layout
```

Arkhe pages will render inside your existing chrome (sidebar, topbar, your CSS).

### 2. Keep your own dashboard

Arkhe ships no dashboard. The backend's landing page belongs to your app — the
starter kits provide one, ready for the figures that matter to you, and the
package has no business replacing it with its own user counters. Those live
where they belong: at the top of the users list.

Nothing to configure. `route('dashboard')` keeps pointing wherever your app
says it does, and the after-login redirect is untouched.

### 3. Inject Arkhe entries into your sidebar

Include the bundled partial at the top level of your `<flux:sidebar.nav>` — it emits the registry-driven groups ("Accès", "Réglages", and any group contributed by a satellite package), so it sits alongside your own groups rather than nested inside one:

```blade
<flux:sidebar.nav>
    <flux:sidebar.group :heading="__('Platform')" class="grid">
        {{-- your custom admin links --}}
        <flux:sidebar.item icon="folder" :href="route('admin.projects.index')" wire:navigate>
            Projects
        </flux:sidebar.item>
    </flux:sidebar.group>

    {{-- Arkhe + satellite-package entries --}}
    @include('arkhe::partials.sidebar-items')
</flux:sidebar.nav>
```

You can also publish the partial to customise it:

```bash
php artisan vendor:publish --tag=arkhe-views
# then edit resources/views/vendor/arkhe/partials/sidebar-items.blade.php
```

### 4. Branch a package onto the shared menu — `ArkheNav`

The sidebar is driven by a navigation registry (`Arkhe\Main\Support\ArkheNav`). Arkhè seeds two sections — `access` ("Accès") and `settings` ("Réglages") — and any package can branch onto the same menu from its service provider's `boot()`, with **no Blade patching**. This is how `adhocrat-io/arkhe-watcher` and future packages plug in.

Add an entry to the shared **Réglages** section (one line per package — the goal: every package's settings in one place):

```php
use Arkhe\Main\Support\ArkheNav;

ArkheNav::section('settings')->item(
    key:    'billing',
    label:  fn () => __('billing::nav.title'),   // closure → resolved at render (locale-aware)
    icon:   'credit-card',
    route:  'billing.settings',
    active: 'billing.settings*',                 // routeIs pattern(s) for the "current" highlight
    can:    'manage-billing',                    // permission string, closure(?$user): bool, or null
    priority: 50,                                // ordering within the section
);
```

Or declare your **own collapsible group** (for a richer tool with several pages):

```php
ArkheNav::section('reports', heading: fn () => __('reports::nav.title'), priority: 90, can: 'view-reports')
    ->item('sales',  fn () => __('reports::nav.sales'),  'chart-bar',  route: 'reports.sales')
    ->item('export', fn () => __('reports::nav.export'), 'arrow-down-tray', route: 'reports.export');
```

A section is rendered only when its gate passes **and** it has at least one visible item; items are filtered per-user by their own `can`. Sections and items order by `priority` (lower first). Because registration is keyed and idempotent, Main and packages can register in any order.

> **A menu `can` only hides the link — it is not access control.** It governs *visibility* in the sidebar, nothing more. You must still protect the destination yourself by gating the package's routes with middleware (`arkhe.backend`, `arkhe.root`, or your own). Treat the menu gate and the route guard as two independent layers and keep them in sync. For example, `adhocrat-io/arkhe-watcher` gates its routes with `arkhe.watcher` (and `arkhe.root` for its settings page) in addition to the matching menu `can`.

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

## Permissions

Permissions are edited from a role's page — there is no separate permissions screen. `/administration/permissions` still resolves, redirecting to the roles list, so links in consumer apps do not break.

Rights are granted **through roles**. A user's own permissions are not editable from the backend: it keeps a single place to look when auditing who can do what. `UserService` still accepts a `permissions` key for programmatic callers — a seeder, a command, your own code — guarded against privilege escalation.

### Grouping the checkboxes — `permission_groups`

A role's page can carry several dozen checkboxes, so they are grouped by resource. Groups are inferred from the `<verb>-<resource>` convention: `view-user`, `create-user` and `manage-users` all land under "users". Anything naming no resource (`access-backend`) goes to "other" rather than being dropped.

To decide the split and the ordering yourself — useful when your permissions do not follow the convention, or when a business grouping reads better:

```php
// config/arkhe.php
'permission_groups' => [
    // A group name that is itself a permission shows at the head of its own group.
    'manage-users' => ['view-user', 'create-user', 'update-user', 'delete-user'],

    // Or a plain label.
    'Content'      => ['view-article', 'publish-article'],
],
```

Only permissions that exist in the database are rendered, so a config running ahead of the seeder never produces empty checkboxes. Whatever the config omits is appended at the end rather than hidden. This is display only — it changes no access rule.

Group labels are translatable under `arkhe::arkhe.permissions.groups`; a missing key falls back to the resource name.

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

## Cookies & GDPR

Arkhe ships [`whitecube/laravel-cookie-consent`](https://github.com/whitecube/laravel-cookie-consent) since 3.1.0. The package's `@cookieconsentscripts` and `@cookieconsentview` blade directives are rendered in the Arkhe layout, gated by `Features::hasCookieConsent()` (defaults to `true`).

### Out-of-the-box behaviour

`Arkhe\Main\Cookies\ArkheCookiesServiceProvider` is registered automatically and declares Laravel's session + CSRF cookies under the **essentials** category. As soon as you install the package, the consent banner appears with a GDPR-compliant baseline — no extra setup required.

### Registering app-specific cookies

For cookies your app sets beyond the essentials (analytics, optional features, …), publish the upstream stub provider and register your own cookies in it:

```bash
php artisan vendor:publish --tag=laravel-cookie-consent-service-provider
php artisan vendor:publish --tag=laravel-cookie-consent-config
```

Then add `App\Providers\CookiesServiceProvider::class` to `bootstrap/providers.php` and edit it as documented [upstream](https://github.com/whitecube/laravel-cookie-consent#registering-cookies):

```php
protected function registerCookies(): void
{
    Cookies::analytics()->google(id: config('services.google_analytics.id'));
    Cookies::optional()->name('darkmode')->duration(120);
}
```

Both providers coexist — Arkhe registers essentials, your provider adds the rest.

### Audit page — `/administration/cookies`

A root-only read-only Livewire page at `/administration/cookies` (route `arkhe.cookies.index`) lists every category and cookie currently registered through both Arkhe and any consumer-side providers. Use it as a GDPR audit trail.

### Disabling the integration

Set `arkhe.features.cookie_consent` to `false` in `config/arkhe.php` to remove the banner directives from the Arkhe layout and skip registering Arkhe's essentials. The upstream package remains installed (`Cookies::hasConsentFor(...)` keeps working) — only the banner is silenced.

## Feature flags

Both SEO and cookie consent became first-class features in 3.1.0 and default to **on**. The flags remain as escape hatches for consumers that want to keep the dependencies installed but silence the integration:

```php
// config/arkhe.php
'features' => [
    'seo'            => true, // SEOData transformer, /administration/seo
    'cookie_consent' => true, // Banner directives, /administration/cookies
],
```

Read them programmatically via `\Arkhe\Main\Support\Features::hasSeo()` / `hasCookieConsent()`.

## Extension points at a glance

Seven layered ways to customise Arkhe without forking it — pick the lightest one that fits:

| # | Lever | Use when |
| --- | --- | --- |
| 1 | **Events** — `UserCreated`, `UserUpdated`, `UserDeleted` (see [Events](#events)) | You need a side-effect (newsletter sync, audit log, webhook) that does NOT need access to the Livewire component state. |
| 2 | **Lifecycle hooks** on the Livewire pages — `beforeSave(array): array`, `afterCreate(Model, array)`, `afterUpdate(Model, array)`, `beforeDelete(Model)` | The side-effect needs UI context — form payload, flash messages, redirects. Override in a subclass (see lever 3). |
| 3 | **Rebindable Livewire components** via `config('arkhe.components')` | You want to subclass any of the nine bundled pages — `ListUsers`, `EditUser`, `ListRoles`, `EditRole`, `ListPermissions`, `SiteSeo`, `Sitemap`, `Cookies`, `StrongAuthRequired` — to add `wire:click` targets or extra fields. The route map auto-resolves to your class. |
| 4 | **`RoleHierarchy::register()`** (runtime) or `config('arkhe.roles')` (static) | You ship a new role from a package or a host module — see [Role hierarchy](#role-hierarchy--authorization). |
| 5 | **Custom permissions** via `config('arkhe.permissions')` + `config('arkhe.role_permissions')`, re-seed with `ArkheRolesSeeder` | You add domain permissions (`manage-posts`, `publish-article`, …) that should live next to Arkhe's bundled set. |
| 6 | **`ArkheNav` navigation registry** — add an item to the shared `settings` section or declare your own group (see [Branch a package onto the shared menu](#4-branch-a-package-onto-the-shared-menu--arkhenav)) | A package needs to contribute sidebar entries that show up in the common backend menu, gated by permission, with no Blade patching. |
| 7 | **Publish the views** (`vendor:publish --tag=arkhe-views`) | The hooks / subclasses are not enough — you need a different Blade structure. Last resort; you take ownership of upgrade diffs. |

Subclass override example (lever 3 + lever 2):

```php
// config/arkhe.php
'components' => [
    'list-users' => App\Livewire\Admin\Users\AppListUsers::class,
],

// app/Livewire/Admin/Users/AppListUsers.php
class AppListUsers extends \Arkhe\Main\Livewire\ListUsers
{
    protected function afterCreate(Model $user, array $payload): void
    {
        app(NewsletterService::class)->subscribe($user, 'admin');
    }

    public function resetPassword(int $id): void
    {
        // extra wire:click target — works because the route already resolves to this class
    }
}
```

No route changes needed in the host app.

## Limitations

Things that may surprise you. None are blockers — most are deliberate trade-offs to keep the installer non-destructive.

| Area | Behaviour |
| --- | --- |
| **Layout default** | `config('arkhe.admin.layout')` defaults to `layouts::app` — a Livewire 4 convention served by the Livewire/Volt and Flux starter kits. On a bare app, set `ARKHE_ADMIN_LAYOUT=arkhe::layouts.app` to fall back on the package's bundled header-only layout, or point it at any view of your own. |
| **Sidebar patch** | Step 8 of `arkhe:main:install` only patches a file matching `*sidebar*.blade.php` that contains `<flux:sidebar.nav>`. No match → silently skipped (the bundled layout uses a `<flux:header>` dropdown, so a sidebar is not strictly required). If your app has multiple sidebar candidates, the installer refuses to choose and you must `@include('arkhe::partials.sidebar-items')` manually. |
| **Tailwind v3** | Step 9 only auto-patches Tailwind v4 (`@import "tailwindcss"` in `resources/css/app.css`). Tailwind v3 setups get a printed snippet for `tailwind.config.js` — patching JS would be too brittle. |
| **User model patch** | Step 10 refuses to inject `HasBackendProfile` if the model already imports `Spatie\Permission\Traits\HasRoles` (it would conflict — `HasBackendProfile` already wraps `HasRoles`). Remove the explicit `use HasRoles;` first, or add `use HasBackendProfile;` by hand. |
| **Layout chrome** | The bundled `arkhe::layouts.app` ships with a Flux header (brand + profile dropdown) but no sidebar, navigation menu, or footer. It's deliberately minimal — to keep its real chrome, override the layout config. |
| **Dark-mode flash** | Some Laravel starter kits hard-code `class="dark"` on the `<html>` tag of their layouts. The page then paints dark before `@fluxAppearance` applies the visitor's real theme — a brief flash, most visible on list pages where the table is the heaviest thing to paint. Not an Arkhe behaviour, but you will see it on Arkhe screens: drop the attribute from every file under `resources/views/layouts/`, auth layouts included, and keep only `lang`. |
| **Strong-auth hand-off** | Arkhe explains the block on its own page, then links out to your security settings — it cannot highlight the right panel once the user is there, since that page belongs to your app. The walkthrough names what to look for instead. If your security page is unusual, override the interstitial (see above) to point at the exact section. |
| **`spatie/laravel-permission` cache** | The seeder calls `Permission::create()` directly. After re-running it (e.g. to add new permissions), clear the permission cache — `php artisan permission:cache-reset` — or restart your queue workers. |
| **Sitemap on `sync` queue** | The "Regenerate now" button dispatches `GenerateSitemap` onto the host app's default queue. With the `sync` driver it runs inline; with a real driver, make sure a worker is up — otherwise the page reports "queued" with no visible progress. |

## Upgrading

### Between minor / patch versions

```bash
composer update adhocrat-io/arkhe-main
php artisan arkhe:main:install   # re-run, answer "no" to steps already done
```

`arkhe:main:install` is idempotent on every step (publish, migrate, seed, sidebar patch, css patch, trait patch). Re-running it after upgrading is the canonical way to pick up new install-time integrations (e.g. a new `@source` to add to `app.css`, a new sidebar entry to inject).

If you'd rather skip the prompts, the manual snippets in the [Styling](#styling--tailwind--flux) and [Wiring up your User model](#wiring-up-your-user-model) sections give you the exact lines to add.

### From V3 to V4

Two breaking changes — the dashboard left the package, and `toArray()` became
`toPayload()` on the four Form objects (shipped in 3.3.0, but an app jumping
3.1 → 4.0 never reads those notes). No PHP you wrote against `Arkhe\Main\…`
needs touching otherwise; a dedicated command handles the rest:

```bash
composer update adhocrat-io/arkhe-main:^4.0
php artisan arkhe:main:upgrade-to-v4 --dry-run   # preview
php artisan arkhe:main:upgrade-to-v4             # apply
```

It removes the three config keys the dashboard removal left dead
(`dashboard_route`, `dashboard_route_name`, `override_fortify_redirect`), banner
comment included. Then it **reports without rewriting** what belongs to you:
published views calling a route the package no longer registers (those throw on
render, not on click), Form subclasses still on `toArray()`, and subclasses
whose overridden hooks are never called any more, saving having moved to
`EditUser` / `EditRole`. The last two fail silently, which is exactly why they
are worth naming.

If you subclass a Form object, rename the method — the payload handed to the
services is identical:

```php
class MyUserForm extends \Arkhe\Main\Livewire\Forms\UserForm
{
    public function toPayload(): array   // was toArray()
    {
        return array_merge(parent::toPayload(), ['team_id' => $this->teamId]);
    }
}
```

`toArray()` keeps the Livewire meaning it must have: it is what serialises the
form into the component snapshot, so overriding it to mean "the fields I
persist" silently drops every other property between two requests.

Coming from `^1` or `^2`? Run `arkhe:main:upgrade-from-v2` first — this command
refuses a V2-shaped config and says so.

### From V2 to V3

V3 keeps the V2 public surface — namespace `Arkhe\Main`, service provider, config prefix — so no global search-replace is required. A dedicated Artisan command handles the config migration:

```bash
composer update adhocrat-io/arkhe-main:^3.0
php artisan arkhe:main:upgrade-from-v2 --dry-run   # preview the changes
php artisan arkhe:main:upgrade-from-v2             # apply
```

What it does:

- Appends V3-only keys to your published `config/arkhe.php` (`role_permissions`, `components`, `backend_permission`, `root_permission`, `features`) without touching existing V2 entries.
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
