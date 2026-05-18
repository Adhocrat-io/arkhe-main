<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    |
    | Path under which the Arkhe backend is mounted. Overridable via .env.
    |
    */
    'route_prefix' => env('ARKHE_ROUTE_PREFIX', 'administration'),

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
    | Layout
    |--------------------------------------------------------------------------
    |
    | The Blade layout wrapping Arkhe's Livewire pages (users, roles,
    | permissions, dashboard). Defaults to `layouts::app`, which Livewire 4
    | registers out of the box as a namespace mapping to
    | `resources/views/layouts/` — the Livewire starter kit's sidebar lives
    | there. Override if your layout lives elsewhere. Set to
    | `arkhe::layouts.app` to fall back to the package's own minimal
    | header-only layout (no sidebar, useful for headless installs).
    |
    */
    'layout' => 'layouts::app',

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
    |   use Adhocrat\Arkhe\Support\RoleHierarchy;
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
