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
    | Role names seeded by the install command and used by the backend
    | access middleware.
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
