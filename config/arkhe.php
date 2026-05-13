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
    */
    'layout' => 'arkhe::layouts.app',

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
        'root'          => 'root',
        'administrator' => 'administrateur',
        'user'          => 'user',
        'guest'         => 'guest',
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
        'seo'            => false,
    ],

];
