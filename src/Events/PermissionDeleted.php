<?php

declare(strict_types=1);

namespace Arkhe\Main\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Spatie\Permission\Models\Permission;

class PermissionDeleted
{
    use Dispatchable;

    public function __construct(public readonly Permission $permission)
    {
    }
}
