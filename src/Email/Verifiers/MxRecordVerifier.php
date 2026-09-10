<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Verifiers;

use Arkhe\Main\Email\Contracts\DnsResolver;
use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Support\EmailModule;
use Illuminate\Support\Facades\Cache;

/**
 * The module's only networked driver, and therefore opt-in per application
 * (FRD § 5.8, NFR-01): `arkhe-email.verifiers.mx.enabled` defaults to
 * false and the guard sits BEFORE any resolution, so disabled provably
 * means silent.
 *
 * The asymmetry is the point: no MX and no A/AAAA (RFC 5321 fallback) is a
 * certainty of non-delivery, but a record present proves nothing — most
 * typo domains are squatted with a catch-all MX — so presence only ever
 * answers Unverifiable. Verdicts are cached both ways; a resolution
 * failure is not, since a transient DNS hiccup must not stick for a day.
 */
final class MxRecordVerifier implements EmailVerifier
{
    private const PRESENT = 'present';

    private const ABSENT = 'absent';

    private const ERROR = 'error';

    public function __construct(private readonly DnsResolver $dns) {}

    public function name(): string
    {
        return 'mx_record';
    }

    public function verify(EmailCandidate $candidate): EmailVerificationResult
    {
        if (! (bool) config('arkhe-email.verifiers.mx.enabled', false)) {
            return $this->unverifiable('driver disabled');
        }

        if ($candidate->domain === '') {
            return $this->unverifiable('no domain to resolve');
        }

        return match ($this->cachedState($candidate->domain)) {
            self::ABSENT => new EmailVerificationResult(
                EmailVerificationStatus::Undeliverable,
                $this->name(),
                reason: 'domain has neither MX nor A/AAAA record',
            ),
            self::PRESENT => $this->unverifiable('mail record present — not proof, many typo domains carry a catch-all MX'),
            default => $this->unverifiable('DNS resolution failed'),
        };
    }

    private function cachedState(string $domain): string
    {
        $key = EmailModule::cacheKey('mx:'.$domain);

        $state = Cache::get($key);

        if (is_string($state)) {
            return $state;
        }

        $state = $this->probe($domain);

        if ($state !== self::ERROR) {
            Cache::put($key, $state, (int) config('arkhe-email.verifiers.mx.ttl', 86400));
        }

        return $state;
    }

    /**
     * Absence must be earned: it takes all three lookups ANSWERING "no
     * record". One lookup failing degrades the whole probe to an error,
     * because "we could not ask" and "the domain has nothing" lead to
     * opposite verdicts.
     */
    private function probe(string $domain): string
    {
        $sawFailure = false;

        foreach (['MX', 'A', 'AAAA'] as $type) {
            $answer = $this->dns->has($domain, $type);

            if ($answer === true) {
                return self::PRESENT;
            }

            $sawFailure = $sawFailure || $answer === null;
        }

        return $sawFailure ? self::ERROR : self::ABSENT;
    }

    private function unverifiable(string $reason): EmailVerificationResult
    {
        return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name(), reason: $reason);
    }
}
