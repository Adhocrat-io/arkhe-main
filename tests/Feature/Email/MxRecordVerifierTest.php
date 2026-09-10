<?php

declare(strict_types=1);

use Arkhe\Main\Email\Contracts\DnsResolver;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Verifiers\MxRecordVerifier;
use Arkhe\Main\Tests\Stubs\Email\FakeDnsResolver;

function fakeDns(array $answers = []): FakeDnsResolver
{
    $fake = new FakeDnsResolver($answers);
    app()->instance(DnsResolver::class, $fake);

    return $fake;
}

function verifyMx(string $email = 'x@probe.example'): EmailVerificationStatus
{
    return app(MxRecordVerifier::class)->verify(new EmailCandidate($email))->status;
}

// Acceptance criterion § 11: the disabled driver provokes NO resolution —
// provable because the fake counts lookups.
it('resolves nothing while disabled', function (): void {
    $dns = fakeDns();

    expect(verifyMx())->toBe(EmailVerificationStatus::Unverifiable)
        ->and($dns->lookups)->toBe(0);
});

it('refuses a domain with no mail record at all', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    fakeDns(); // every answer: no record

    expect(verifyMx())->toBe(EmailVerificationStatus::Undeliverable);
});

// RFC 5321: a domain without MX but with an A/AAAA still receives mail.
it('accepts the A record fallback as presence', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    fakeDns(['probe.example/A' => true]);

    expect(verifyMx())->toBe(EmailVerificationStatus::Unverifiable);
});

// A record present proves nothing — typo domains carry catch-all MX (§ 5.8).
it('never answers deliverable from a present record', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    fakeDns(['probe.example/MX' => true]);

    expect(verifyMx())->toBe(EmailVerificationStatus::Unverifiable);
});

it('degrades a failed lookup to unverifiable, never to a verdict', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    // MX answered "none", A failed: absence is no longer provable.
    fakeDns(['probe.example/A' => null]);

    expect(verifyMx())->toBe(EmailVerificationStatus::Unverifiable);
});

// ─── Cache (§ 5.8): verdicts stick, failures do not ──────────────────────

it('caches negative verdicts', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    $dns = fakeDns();

    verifyMx();
    $after = $dns->lookups;
    verifyMx();

    expect($dns->lookups)->toBe($after);
});

it('caches positive verdicts', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    $dns = fakeDns(['probe.example/MX' => true]);

    verifyMx();
    verifyMx();

    expect($dns->lookups)->toBe(1);
});

it('does not cache resolution failures', function (): void {
    config()->set('arkhe-email.verifiers.mx.enabled', true);
    $dns = fakeDns(['probe.example/MX' => null]);

    verifyMx();
    $first = $dns->lookups;
    verifyMx();

    // The second call probed again: a transient DNS hiccup must not stick
    // for a whole TTL.
    expect($dns->lookups)->toBeGreaterThan($first);
});
