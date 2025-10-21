<?php

declare(strict_types=1);

namespace Arkhe\Main\DataTransferObjects;

class RoleDto
{
    public function __construct(
        public string $name,
        public string $label,
        public string $guard_name,
        public array $permissions = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'guard_name' => $this->guard_name,
            'permissions' => $this->permissions,
        ];
    }
}
