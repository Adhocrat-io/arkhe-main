<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs;

use Arkhe\Main\Concerns\HasBackendProfile;
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
