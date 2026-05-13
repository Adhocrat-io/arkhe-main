<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class UserUpdated
{
    use Dispatchable;

    public function __construct(public readonly Model $user)
    {
    }
}
