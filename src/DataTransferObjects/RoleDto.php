<?php

declare(strict_types=1);

namespace Arkhe\Main\DataTransferObjects;

use Illuminate\Support\Collection;

class RoleDto
{
    public function __construct(
        public string $name,
        public string $label,
        public string $guard_name,
        public ?Collection $permissions,
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
