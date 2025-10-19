<?php

declare(strict_types=1);

namespace Arkhe\Main\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class ResetUserPasswordAction
{
    public function handle(User $user): void
    {
        $token = Str::random(60);
        $user->sendPasswordResetNotification($token);
        event(new PasswordReset($user));
    }
}
