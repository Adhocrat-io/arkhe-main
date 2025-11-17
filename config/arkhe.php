<?php

declare(strict_types=1);

use Arkhe\Main\Enums\Users\UserRoleEnum;

return [
    'admin' => [
        'prefix' => env('ARKHE_ADMIN_PREFIX', 'administration'),
        'roles' => [ // Roles allowed to access the administration
            UserRoleEnum::ROOT->value,
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::EDITORIAL->value,
            UserRoleEnum::AUTHOR->value,
            UserRoleEnum::CONTRIBUTOR->value,
        ],
    ],
    'permissions' => [
        'manage-users' => [
            'view-user',
            'create-user',
            'update-user',
            'delete-user',
        ],
        'manage-roles' => [
            'view-role',
            'create-role',
            'update-role',
            'delete-role',
        ],
        'manage-customizations' => [
            'view-customization',
            'create-customization',
            'update-customization',
            'delete-customization',
        ],
        'manage-subscriptions' => [
            'view-subscription',
            'create-subscription',
            'update-subscription',
            'delete-subscription',
        ],
        'manage-settings' => [
            'view-setting',
            'update-setting',
            'delete-setting',
        ],
    ],
    'roles' => [
        UserRoleEnum::ROOT->value => [
            '*'
        ],
        UserRoleEnum::ADMIN->value => [
            '*'
        ],
        UserRoleEnum::AUTHOR->value => [
            'arkhe.permissions.posts.*',
        ],
        UserRoleEnum::CONTRIBUTOR->value => [
            'arkhe.permissions.posts.*',
        ],
        UserRoleEnum::EDITORIAL->value => [
            'arkhe.permissions.posts.*',
        ],
        UserRoleEnum::SUBSCRIBER->value => [
            'arkhe.permissions.subscriptions.*',
        ],
        UserRoleEnum::GUEST->value => [
            'arkhe.permissions.settings.*',
        ],
    ]
];
