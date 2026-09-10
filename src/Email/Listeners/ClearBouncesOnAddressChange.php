<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Listeners;

use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Events\UserUpdated;

/**
 * Rehabilitation without a button (FRD § 8.4): a user who edits their
 * address is attesting it works, so any suppression on the NEW value is
 * lifted. The old address keeps its history — it earned it, and nobody is
 * mailing it anymore.
 */
final class ClearBouncesOnAddressChange
{
    public function __construct(private readonly EmailBounceRepository $bounces) {}

    public function handle(UserUpdated $event): void
    {
        if (! $event->user->wasChanged('email')) {
            return;
        }

        $email = $event->user->getAttribute('email');

        if (is_string($email) && $email !== '') {
            $this->bounces->clear($email);
        }
    }
}
