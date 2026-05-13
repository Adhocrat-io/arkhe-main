<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Tests\Stubs;

use Adhocrat\Arkhe\Concerns\HasBackendProfile;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasBackendProfile;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'date_of_birth'     => 'date',
        'email_verified_at' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];
}
