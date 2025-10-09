<?php

declare(strict_types=1);

namespace Arkhe\Main\Enums\Users;

use App\Models\User;

enum UserRoleEnum: string
{
    case ROOT = 'root';
    case ADMIN = 'admin';
    case EDITORIAL = 'editorial';
    case AUTHOR = 'author';
    case CONTRIBUTOR = 'contributor';
    case SUBSCRIBER = 'subscriber';
    case GUEST = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::ROOT => __('Root'),
            self::ADMIN => __('Admin'),
            self::EDITORIAL => __('Editorial'),
            self::AUTHOR => __('Author'),
            self::CONTRIBUTOR => __('Contributor'),
            self::SUBSCRIBER => __('Subscriber'),
            self::GUEST => __('Guest'),
        };
    }

    public function getAllowedRoles(): array
    {
        return match ($this) {
            self::ROOT => [
                self::ROOT->value,
                self::ADMIN->value,
                self::EDITORIAL->value,
                self::AUTHOR->value,
                self::CONTRIBUTOR->value,
                self::SUBSCRIBER->value,
                self::GUEST->value,
            ],
            self::ADMIN => [
                self::ADMIN->value,
                self::EDITORIAL->value,
                self::AUTHOR->value,
                self::CONTRIBUTOR->value,
                self::SUBSCRIBER->value,
                self::GUEST->value,
            ],
            self::EDITORIAL => [
                self::EDITORIAL->value,
                self::AUTHOR->value,
            ],
            self::AUTHOR => [
                self::AUTHOR->value,
            ],
            self::CONTRIBUTOR => [
                self::CONTRIBUTOR->value,
            ],
            self::SUBSCRIBER => [
                self::SUBSCRIBER->value,
            ],
            self::GUEST => [
                self::GUEST->value,
            ],
        };
    }

    public static function fromUser(User $user): self
    {
        $userRole = $user->roles->first()?->name;

        return $userRole ? self::tryFrom($userRole) : self::GUEST;
    }
}
