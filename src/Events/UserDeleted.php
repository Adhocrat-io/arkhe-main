<?php

declare(strict_types=1);

namespace Arkhe\Main\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class UserDeleted
{
    use Dispatchable;

    public function __construct(public readonly Model $user)
    {
    }
}
