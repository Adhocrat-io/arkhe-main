<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Services;

use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\BounceType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The single entry point every bounce goes through — webhooks, the manual
 * command, whatever comes later (FRD § 8.2). Recording and learning stay
 * one call so no adapter can feed the history without feeding the domain
 * knowledge.
 */
final class BounceIngestionService
{
    public function __construct(
        private readonly EmailBounceRepository $bounces,
        private readonly DomainLearningService $learning,
    ) {}

    public function ingest(string $email, BounceType $type, string $provider, ?string $reason, CarbonImmutable $at): void
    {
        $this->bounces->record($email, $type, $provider, $reason, $at);

        $domain = (new EmailCandidate($email))->domain;

        if ($domain !== '') {
            $this->learning->recordBounce($domain, $type);
        }

        Log::info('[arkhe-email] Bounce recorded.', [
            'type' => $type->value,
            'provider' => $provider,
            // The domain, never the address: log lines travel further than
            // databases do.
            'domain' => $domain,
        ]);
    }
}
