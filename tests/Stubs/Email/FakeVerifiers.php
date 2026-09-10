<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs\Email;

use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use RuntimeException;

/**
 * One tiny driver per verdict, each counting its calls so tests can assert
 * what the pipeline actually ran — the aggregation rules of FRD § 5.3 are
 * about ordering and interruption, not just about the final status.
 */
abstract class CountingVerifier implements EmailVerifier
{
    public static int $calls = 0;

    public static function reset(): void
    {
        static::$calls = 0;
    }

    public function verify(EmailCandidate $candidate): EmailVerificationResult
    {
        static::$calls++;

        return $this->result($candidate);
    }

    public function name(): string
    {
        return static::class;
    }

    abstract protected function result(EmailCandidate $candidate): EmailVerificationResult;
}

class AlwaysDeliverable extends CountingVerifier
{
    public static int $calls = 0;

    protected function result(EmailCandidate $candidate): EmailVerificationResult
    {
        return new EmailVerificationResult(EmailVerificationStatus::Deliverable, $this->name());
    }
}

class AlwaysRisky extends CountingVerifier
{
    public static int $calls = 0;

    protected function result(EmailCandidate $candidate): EmailVerificationResult
    {
        return new EmailVerificationResult(EmailVerificationStatus::Risky, $this->name(), suggestion: 'corrected@example.com');
    }
}

class AlwaysUndeliverable extends CountingVerifier
{
    public static int $calls = 0;

    protected function result(EmailCandidate $candidate): EmailVerificationResult
    {
        return new EmailVerificationResult(EmailVerificationStatus::Undeliverable, $this->name());
    }
}

class UndeliverableWithSuggestion extends CountingVerifier
{
    public static int $calls = 0;

    protected function result(EmailCandidate $candidate): EmailVerificationResult
    {
        return new EmailVerificationResult(
            EmailVerificationStatus::Undeliverable,
            $this->name(),
            suggestion: $candidate->withDomain('example.com'),
        );
    }
}

class AlwaysUnverifiable extends CountingVerifier
{
    public static int $calls = 0;

    protected function result(EmailCandidate $candidate): EmailVerificationResult
    {
        return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name());
    }
}

class ThrowingVerifier extends CountingVerifier
{
    public static int $calls = 0;

    protected function result(EmailCandidate $candidate): EmailVerificationResult
    {
        throw new RuntimeException('driver exploded');
    }
}
