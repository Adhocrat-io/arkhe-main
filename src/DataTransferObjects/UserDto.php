<?php

declare(strict_types=1);

namespace Arkhe\Main\DataTransferObjects;

use Carbon\Carbon;

class UserDto
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly ?Carbon $date_of_birth,
        public readonly ?string $civility,
        public readonly ?string $profession,
        public readonly ?string $password,
        public readonly ?string $role,
    ) {}

    public function toArray(): array
    {
        $data = [
            'username' => $this->username,
            'email' => $this->email,
            'date_of_birth' => $this->date_of_birth,
            'civility' => $this->civility,
            'profession' => $this->profession,
            'role' => $this->role,
        ];

        if ($this->password) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
