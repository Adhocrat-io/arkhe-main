<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin area
    |--------------------------------------------------------------------------
    |
    | Settings grouped under `admin.*` describe the backend mount-point. The
    | nested shape mirrors the V2 (Arkhe\Main) layout so existing host-app
    | config files keep working without modification.
    |
    | - `admin.prefix`  route segment for backend pages (env: ARKHE_ADMIN_PREFIX).
    | - `admin.layout`  Blade layout wrapping every Livewire page.
    | - `admin.roles`   role keys (from `roles` below) granted access by the
    |                   `arkhe.backend` middleware in addition to the
    |                   permission check. Empty array = permission-only.
    |
    */
    'admin' => [
        'prefix' => env('ARKHE_ADMIN_PREFIX', env('ARKHE_ROUTE_PREFIX', 'administration')),
        'layout' => env('ARKHE_ADMIN_LAYOUT', 'layouts::app'),
        'roles'  => ['root', 'administrator'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware stack applied to all backend routes.
    |
    */
    'middleware' => ['web', 'auth', 'arkhe.backend'],

    /*
    |--------------------------------------------------------------------------
    | Avatar storage
    |--------------------------------------------------------------------------
    */
    'avatar_disk' => env('ARKHE_AVATAR_DISK', 'public'),
    'avatar_path' => env('ARKHE_AVATAR_PATH', 'avatars'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'per_page' => 15,

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | If null, resolves to config('auth.providers.users.model').
    |
    */
    'user_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Role names seeded by the install command, used by the backend access
    | middleware AND by the role hierarchy. The ORDER of this array IS the
    | hierarchy: the first entry is the highest rank, the last is the lowest.
    |
    | To insert a new role at a given rank, just add it at the right position
    | between two existing entries — both the seeder and the hierarchy will
    | pick it up:
    |
    |   'roles' => [
    |       'root'          => 'root',
    |       'administrator' => 'administrateur',
    |       'manager'       => 'manager',   // new role between admin and user
    |       'user'          => 'user',
    |       'guest'         => 'guest',
    |   ],
    |
    | Alternatively, register roles at runtime from a service provider:
    |
    |   use Arkhe\Main\Support\RoleHierarchy;
    |
    |   RoleHierarchy::register('manager', after: 'administrateur');
    |
    */
    'roles' => [
        'root' => 'root',
        'administrator' => 'administrateur',
        'user' => 'user',
        'guest' => 'guest',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | The flat list of permissions seeded by `arkhe:main:install`. Roles are
    | the *container*; permissions are what the app actually checks. Add
    | application-specific permissions here (or in a separate seeder) and they
    | will be created alongside Arkhe's own.
    |
    | The convention is `manage-<resource>` for the broad shortcut, and
    | `<verb>-<resource>` (view/create/update/delete) for the fine-grained
    | actions. Blade & controllers can use either:
    |
    |   @can('manage-users')   ...   @endcan
    |   @can('update-user')    ...   @endcan
    |
    */
    'permissions' => [
        'access-backend',

        'manage-users',
        'view-user',
        'create-user',
        'update-user',
        'delete-user',

        'manage-roles',
        'view-role',
        'create-role',
        'update-role',
        'delete-role',

        'manage-permissions',
        'view-permission',
        'create-permission',
        'update-permission',
        'delete-permission',

        'manage-site-seo',
        'view-site-seo',
        'update-site-seo',

        'manage-sitemap',
        'view-sitemap',
        'update-sitemap',

        'view-cookies',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role → permissions mapping (used by the seeder)
    |--------------------------------------------------------------------------
    |
    | Indexed by the *key* of `roles` above (root/administrator/user/guest),
    | NOT the localised value — so renaming `administrator => 'admin'` does
    | not break the grant map. Use the literal `'*'` to grant every
    | permission registered in `permissions` (typical for root).
    |
    */
    'role_permissions' => [
        'root' => ['*'],

        'administrator' => [
            'access-backend',
            'manage-users',
            'view-user',
            'create-user',
            'update-user',
            'delete-user',
        ],

        'user'  => [],
        'guest' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backend access permission
    |--------------------------------------------------------------------------
    |
    | The permission checked by the `arkhe.backend` middleware. Any user with
    | this permission (regardless of their role) reaches the backend; users
    | without it get a 403.
    |
    */
    'backend_permission' => 'access-backend',

    /*
    |--------------------------------------------------------------------------
    | Root-area permission
    |--------------------------------------------------------------------------
    |
    | Permission checked by the `arkhe.root` middleware — the gate for the
    | sensitive zone (roles & permissions management). Historically tied to
    | the `root` role; now an explicit permission so any custom role you
    | grant it to can reach it.
    |
    */
    'root_permission' => 'manage-roles',

    /*
    |--------------------------------------------------------------------------
    | Strong authentication
    |--------------------------------------------------------------------------
    |
    | Requires one strong factor before reaching the backend: a registered
    | passkey OR a confirmed TOTP. A passkey exempts from TOTP — it is already
    | two-factor and, being bound to the domain, phishing-resistant where a
    | TOTP code is not.
    |
    | This gates access to the backend, not the sign-in itself: how users
    | authenticate belongs to your app's Fortify pipeline. A user without a
    | factor stays signed in and keeps the rest of the site — only the admin
    | area closes until they enrol.
    |
    |   'enforce'  false  no enforcement. The default: upgrading the package
    |                     must never lock an app out of its own backend
    |              true   every backend page demands a strong factor
    |
    | All or nothing, on purpose. Guarding the sensitive area alone would leave
    | the user list open — where accounts are created and roles handed out —
    | which reads as prudence but mostly buys a false sense of safety. Roles and
    | permissions already draw that line where it belongs.
    |
    |   'route'    route name of the page where users enrol. Left null, Arkhe
    |              probes `security.edit` then `two-factor.show`, so current
    |              starter kits work with no configuration at all. Set it if
    |              your app names that page differently.
    |
    | Enrol before switching this on: turning it on without a factor bounces
    | you to the explanation page on every attempt. Nothing breaks — it leads
    | to your security page and a minute fixes it — but the other order is more
    | comfortable.
    |
    | A blocked user lands on `/administration/strong-auth`, an Arkhe page that
    | states the requirement and links on to the enrolment screen. Override it
    | through `components.strong-auth-required` like any other page.
    |
    | Two degraded cases are handled rather than left to fail. If no enrolment
    | page can be resolved, that page reports what to configure instead of
    | linking nowhere. And if the user model exposes neither mechanism, the
    | requirement is unsatisfiable by anyone, so it is skipped with a logged
    | warning — blocking a backend nobody could re-enter would be worse than
    | leaving it as it was.
    |
    */
    'strong_auth' => [
        'enforce' => env('ARKHE_STRONG_AUTH', false),
        'route'   => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire components
    |--------------------------------------------------------------------------
    |
    | Class map for the four Livewire pages Arkhe registers under the
    | `arkhe.<alias>` namespace. Override any entry with your own subclass to
    | add fields, buttons or actions while keeping Arkhe's wiring (routes,
    | views, services). Typical override:
    |
    |   'components' => [
    |       'list-users' => App\Livewire\Admin\Users\RevelListUsers::class,
    |   ],
    |
    | Your subclass extends the Arkhe one, adds new public methods (`wire:click`
    | targets) and/or overrides the lifecycle hooks (`beforeSave`,
    | `afterCreate`, `afterUpdate`, `beforeDelete`). Routes auto-resolve to
    | the configured class — no need to redeclare them.
    |
    */
    'components' => [
        'list-users'       => \Arkhe\Main\Livewire\ListUsers::class,
        'edit-user'        => \Arkhe\Main\Livewire\EditUser::class,
        'list-roles'       => \Arkhe\Main\Livewire\ListRoles::class,
        'edit-role'        => \Arkhe\Main\Livewire\EditRole::class,
        'list-permissions' => \Arkhe\Main\Livewire\ListPermissions::class,
        'site-seo'         => \Arkhe\Main\Livewire\SiteSeo::class,
        'sitemap'          => \Arkhe\Main\Livewire\Sitemap::class,
        'cookies'          => \Arkhe\Main\Livewire\Cookies::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    |
    | Toggles for phase 2 features (cookie consent, SEO). Disabled by
    | default — wired up in src/Support/Features.php.
    |
    */
    'features' => [
        'cookie_consent' => true,
        'seo' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | Automatic sitemap generation via spatie/laravel-sitemap. The package
    | crawls `url` and writes to `path`. The cron expression below schedules
    | a daily run; set `enabled` to false to skip scheduling entirely (the
    | manual "Generate now" button on /administration/sitemap still works).
    |
    */
    'sitemap' => [
        'enabled'  => env('ARKHE_SITEMAP_ENABLED', true),
        'url'      => env('ARKHE_SITEMAP_URL'),       // defaults to config('app.url')
        'path'     => env('ARKHE_SITEMAP_PATH'),      // defaults to public_path('sitemap.xml')
        'schedule' => env('ARKHE_SITEMAP_SCHEDULE', '0 3 * * *'),
    ],

];
