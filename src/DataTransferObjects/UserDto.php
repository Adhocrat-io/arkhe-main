<?php

declare(strict_types=1);

namespace Arkhe\Main\DataTransferObjects;

use Carbon\Carbon;

class UserDto
{
    public function __construct(
        public ?string $first_name,
        public string $last_name,
        public string $email,
        public ?Carbon $date_of_birth,
        public ?string $civility,
        public ?string $profession,
        public ?string $password,
        public ?string $role,
    ) {}

    public function toArray(): array
    {
        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
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
