<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Verifiers;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;

/**
 * Asks the `email_domains` table what it knows (FRD § 5.5). The only driver
 * that can answer `Deliverable` — and only because a human or a confirmed
 * delivery put the domain on the allowed list: positive proof, not absence
 * of alarm.
 */
final class DomainListVerifier implements EmailVerifier
{
    public function __construct(private readonly EmailDomainRepository $domains) {}

    public function name(): string
    {
        return 'domain_list';
    }

    public function verify(EmailCandidate $candidate): EmailVerificationResult
    {
        $entry = $this->domains->find($candidate->domain);

        if ($entry === null) {
            return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name(), reason: 'domain unknown');
        }

        return match ($entry->status) {
            DomainStatus::Allowed => new EmailVerificationResult(
                EmailVerificationStatus::Deliverable,
                $this->name(),
                reason: 'domain on the allowed list',
            ),
            DomainStatus::Blocked => new EmailVerificationResult(
                EmailVerificationStatus::Undeliverable,
                $this->name(),
                reason: 'domain on the blocked list',
            ),
            DomainStatus::Typo => new EmailVerificationResult(
                EmailVerificationStatus::Risky,
                $this->name(),
                suggestion: $candidate->withDomain((string) $entry->correctedDomain),
                reason: 'domain is a known typo of '.$entry->correctedDomain,
            ),
        };
    }
}
