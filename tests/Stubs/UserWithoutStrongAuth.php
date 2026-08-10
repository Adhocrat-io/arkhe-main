<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs;

use Arkhe\Main\Concerns\HasBackendProfile;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model from an app that installed neither Fortify nor laravel/passkeys.
 *
 * Exists purely so the "requirement is unsatisfiable" path can be tested: you
 * cannot exercise "the model exposes no mechanism" with a model that exposes
 * one. Deliberately identical to {@see User} minus the two probe methods.
 */
class UserWithoutStrongAuth extends Authenticatable
{
    use HasBackendProfile;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];
}
