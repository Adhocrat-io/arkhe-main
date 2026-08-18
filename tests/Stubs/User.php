<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs;

use Arkhe\Main\Concerns\HasBackendProfile;
use Arkhe\Main\Support\StrongAuth;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasBackendProfile;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'date_of_birth'           => 'date',
        'email_verified_at'       => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Mirrors Laravel\Fortify\TwoFactorAuthenticatable under `confirm => true`,
     * which is what the starter kits ship: a secret alone is not enough, the
     * user must have completed the confirmation step.
     *
     * Hand-written rather than pulled from the trait because the package does
     * not depend on Fortify — and it must not, since the gate probes for the
     * method, never for the trait. Writing it out here proves the probe works
     * against any model exposing the same contract.
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Mirrors Laravel\Passkeys\PasskeyAuthenticatable: an `exists()` query per
     * call, with no memoization of its own. That cost is the reason
     * {@see StrongAuth} probes two-factor first and caches
     * the verdict for the rest of the request.
     */
    public function hasPasskeysEnabled(): bool
    {
        return DB::table('passkeys')->where('user_id', $this->getKey())->exists();
    }
}
