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
    | Dashboard route (opt-in)
    |--------------------------------------------------------------------------
    |
    | When set, Arkhe registers a top-level dashboard at this path with the
    | named route `arkhe.dashboard`. Leave null to keep your app's existing
    | dashboard untouched. Typical value: `dashboard`.
    |
    */
    'dashboard_route' => env('ARKHE_DASHBOARD_ROUTE'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard route name
    |--------------------------------------------------------------------------
    |
    | Named route under which the dashboard is registered. Defaults to
    | `arkhe.dashboard`. Set to `dashboard` (via ARKHE_DASHBOARD_ROUTE_NAME)
    | so the Laravel starter kit's `route('dashboard')` after-login redirect
    | resolves to Arkhe's dashboard without having to patch the login form.
    |
    */
    'dashboard_route_name' => env('ARKHE_DASHBOARD_ROUTE_NAME', 'arkhe.dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Override Fortify's `home` redirect
    |--------------------------------------------------------------------------
    |
    | When Fortify is installed and `dashboard_route` is set, Arkhe rewrites
    | `config('fortify.home')` at boot so the post-login (and post-2FA, post
    | password confirm) redirect lands on the Arkhe dashboard instead of the
    | starter kit's hard-coded `/dashboard`. Disable if you want to manage
    | Fortify's redirect yourself.
    |
    */
    'override_fortify_redirect' => env('ARKHE_OVERRIDE_FORTIFY_REDIRECT', true),

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
        'list-roles'       => \Arkhe\Main\Livewire\ListRoles::class,
        'list-permissions' => \Arkhe\Main\Livewire\ListPermissions::class,
        'dashboard'        => \Arkhe\Main\Livewire\Dashboard::class,
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
        'cookie_consent' => false,
        'seo' => false,
    ],

];
