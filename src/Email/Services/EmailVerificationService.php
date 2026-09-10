<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Services;

use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Support\EmailModule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the configured drivers over a candidate and aggregates their answers
 * (FRD § 5.3).
 *
 * The aggregation is a priority, not a race: `Undeliverable` interrupts (it
 * is a certainty, nothing later can soften it), but `Risky` outranks
 * `Deliverable` even when the latter came first. That ordering is the whole
 * point — `jena@hotmail.com` gets a `Deliverable` from the domain list and a
 * `Risky` from the name comparison, and the user must see the suggestion,
 * not a green light for a typo'd local part.
 */
final class EmailVerificationService
{
    public function __construct(private readonly Container $container) {}

    public function verify(EmailCandidate $candidate, ?string $context = null): EmailVerificationResult
    {
        $firstRisky = null;
        $firstDeliverable = null;
        $result = null;

        foreach ($this->drivers() as $driver) {
            try {
                $verdict = $driver->verify($candidate);
            } catch (Throwable $e) {
                // A broken driver must never decide anything, in either
                // direction — and it must not take the rest of the pipeline
                // down with it: the drivers after it may still have a real
                // answer.
                Log::warning('[arkhe-email] Verifier threw; treating as unverifiable.', [
                    'driver' => $driver->name(),
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            if ($verdict->status === EmailVerificationStatus::Undeliverable) {
                $result = $verdict;

                break;
            }

            $firstRisky ??= $verdict->status === EmailVerificationStatus::Risky ? $verdict : null;
            $firstDeliverable ??= $verdict->status === EmailVerificationStatus::Deliverable ? $verdict : null;
        }

        $result ??= $firstRisky
            ?? $firstDeliverable
            ?? new EmailVerificationResult(EmailVerificationStatus::Unverifiable, 'pipeline');

        $this->journal($candidate, $result, $context);

        return $result;
    }

    /**
     * @return list<EmailVerifier>
     */
    private function drivers(): array
    {
        $drivers = [];

        foreach ((array) config('arkhe-email.verifiers.pipeline', []) as $class) {
            // A stale class name in a published config must degrade to "one
            // driver less", not to a fatal error inside every validation.
            if (! is_string($class) || ! class_exists($class)) {
                Log::warning('[arkhe-email] Unknown verifier in pipeline; skipping.', ['class' => $class]);

                continue;
            }

            $driver = $this->container->make($class);

            if ($driver instanceof EmailVerifier) {
                $drivers[] = $driver;
            }
        }

        return $drivers;
    }

    /**
     * In `observe` and `suggest`, every pipeline verdict is journaled so
     * `arkhe:email:report` can measure the false-positive rate on the
     * client's real audience BEFORE anyone is blocked (FRD § 14.2). Only the
     * domain is logged — never the full address nor the local part: the
     * journal must stay shareable without becoming personal data.
     */
    private function journal(EmailCandidate $candidate, EmailVerificationResult $result, ?string $context): void
    {
        if (! EmailModule::enabled() || EmailModule::mode() === EmailVerificationMode::Enforce) {
            return;
        }

        Log::channel((string) config('arkhe-email.log_channel', 'arkhe-email'))->info('pipeline', [
            'context' => $context ?? '-',
            'status' => $result->status->value,
            'driver' => $result->driver,
            'has_suggestion' => $result->suggestion !== null,
            'domain' => $candidate->domain,
        ]);
    }
}
