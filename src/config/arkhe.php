<?php

declare(strict_types=1);

use Arkhe\Main\Enums\Users\UserRoleEnum;

return [
    'admin' => [
        'prefix' => env('ARKHE_ADMIN_PREFIX', 'administration'),
        'roles' => [ // Rôles autorisés à accéder à l'administration
            UserRoleEnum::ROOT->value,
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::EDITORIAL->value,
        ],
    ],
];
