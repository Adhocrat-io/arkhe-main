<?php

declare(strict_types=1);

namespace Arkhe\Main\DataTransferObjects;

class RoleDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $guard_name,
        public readonly array $permissions = [],
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
