<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Verifiers;

use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;

/**
 * The cheapest certainty there is: the receiving server already told us
 * this exact address does not exist (FRD § 5.4). First in the pipeline —
 * no distance, no list, no DNS beats a recorded hard bounce.
 *
 * Only hard bounces and complaints count here; soft trouble (full mailbox,
 * greylisting) says nothing about whether the address exists.
 */
final class BounceHistoryVerifier implements EmailVerifier
{
    public function __construct(private readonly EmailBounceRepository $bounces) {}

    public function name(): string
    {
        return 'bounce_history';
    }

    public function verify(EmailCandidate $candidate): EmailVerificationResult
    {
        if ($this->bounces->hasActiveHardBounce($candidate->email)) {
            return new EmailVerificationResult(
                EmailVerificationStatus::Undeliverable,
                $this->name(),
                reason: 'address has an uncleared hard bounce on record',
            );
        }

        return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name());
    }
}
