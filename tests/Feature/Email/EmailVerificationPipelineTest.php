<?php

declare(strict_types=1);

use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Services\EmailVerificationService;
use Arkhe\Main\Tests\Stubs\Email\AlwaysDeliverable;
use Arkhe\Main\Tests\Stubs\Email\AlwaysRisky;
use Arkhe\Main\Tests\Stubs\Email\AlwaysUndeliverable;
use Arkhe\Main\Tests\Stubs\Email\AlwaysUnverifiable;
use Arkhe\Main\Tests\Stubs\Email\ThrowingVerifier;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    foreach ([AlwaysDeliverable::class, AlwaysRisky::class, AlwaysUndeliverable::class, AlwaysUnverifiable::class, ThrowingVerifier::class] as $stub) {
        $stub::reset();
    }
});

function runPipeline(array $drivers, string $email = 'jean@example.com'): EmailVerificationResult
{
    config()->set('arkhe-email.verifiers.pipeline', $drivers);

    return app(EmailVerificationService::class)->verify(new EmailCandidate($email));
}

// ─── Aggregation (FRD § 5.3) ─────────────────────────────────────────────

it('interrupts on the first undeliverable', function (): void {
    $result = runPipeline([AlwaysUndeliverable::class, AlwaysRisky::class]);

    expect($result->status)->toBe(EmailVerificationStatus::Undeliverable)
        ->and(AlwaysRisky::$calls)->toBe(0);
});

// The headline rule: risky OUTRANKS deliverable even when deliverable came
// first. `jena@hotmail.com` gets a green light from the domain list and a
// suggestion from the name check — the user must see the suggestion.
it('prefers a risky over an earlier deliverable', function (): void {
    $result = runPipeline([AlwaysDeliverable::class, AlwaysRisky::class]);

    expect($result->status)->toBe(EmailVerificationStatus::Risky)
        ->and($result->suggestion)->toBe('corrected@example.com');
});

it('answers deliverable when nothing risky appeared', function (): void {
    $result = runPipeline([AlwaysUnverifiable::class, AlwaysDeliverable::class]);

    expect($result->status)->toBe(EmailVerificationStatus::Deliverable);
});

it('falls back to unverifiable signed by the pipeline itself', function (): void {
    $result = runPipeline([AlwaysUnverifiable::class]);

    expect($result->status)->toBe(EmailVerificationStatus::Unverifiable)
        ->and($result->driver)->toBe('pipeline');
});

// ─── Robustness ──────────────────────────────────────────────────────────

// A broken driver is logged and skipped: it must neither decide anything nor
// take down the drivers after it (FRD § 5.3).
it('survives a throwing driver and keeps going', function (): void {
    Log::spy();

    $result = runPipeline([ThrowingVerifier::class, AlwaysRisky::class]);

    expect($result->status)->toBe(EmailVerificationStatus::Risky)
        ->and(AlwaysRisky::$calls)->toBe(1);

    Log::shouldHaveReceived('warning')->once();
});

it('skips an unknown class in the configured pipeline', function (): void {
    Log::spy();

    $result = runPipeline(['App\\Gone\\Verifier', AlwaysDeliverable::class]);

    expect($result->status)->toBe(EmailVerificationStatus::Deliverable);
});

// ─── Journal (FRD § 14.2) ────────────────────────────────────────────────

// The journal is what `arkhe:email:report` aggregates: one structured line
// per verdict, carrying the domain but NEVER the full address (§ 14.2).
it('journals every verdict in observe mode, domain only', function (): void {
    config()->set('arkhe-email.enabled', true);
    config()->set('arkhe-email.mode', 'observe');

    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('info')->once()->withArgs(
        fn (string $message, array $context): bool => $context['domain'] === 'hotmail.com'
            && $context['status'] === 'risky'
            && ! str_contains(json_encode($context), 'jena@'),
    );

    Log::shouldReceive('channel')->once()->with('arkhe-email')->andReturn($channel);

    runPipeline([AlwaysRisky::class], 'jena@hotmail.com');
});

it('does not journal while the module is disabled', function (): void {
    config()->set('arkhe-email.enabled', false);

    Log::shouldReceive('channel')->never();

    runPipeline([AlwaysDeliverable::class]);
});

it('does not journal in enforce mode', function (): void {
    config()->set('arkhe-email.enabled', true);
    config()->set('arkhe-email.mode', 'enforce');

    Log::shouldReceive('channel')->never();

    runPipeline([AlwaysDeliverable::class]);
});
