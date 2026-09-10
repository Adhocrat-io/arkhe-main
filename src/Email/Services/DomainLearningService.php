<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Services;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Models\EmailBounce;

/**
 * Turns the bounce loop into domain knowledge (FRD § 6.4). Three rules,
 * one principle — evidence outranks verdicts:
 *
 *  - `block_threshold` hard bounces with not one delivery block a domain;
 *  - one successful delivery unblocks a learned block (it was wrong);
 *  - `manual` rows keep their counters ticking but never change status —
 *    a human decided, the machine records.
 *
 * Learning never creates a `typo` entry: guessing the intended domain is
 * the curated lists' job, not a statistic's.
 */
final class DomainLearningService
{
    public function __construct(private readonly EmailDomainRepository $domains) {}

    public function recordBounce(string $domain, BounceType $type): void
    {
        // Soft bounces say "not now", nothing about the domain (§ 8.2).
        if ($type === BounceType::Soft) {
            return;
        }

        $entry = $this->domains->find($domain);

        if ($entry !== null) {
            $this->domains->incrementBounces($domain);
            $this->maybeBlock($this->domains->find($domain));

            return;
        }

        // Unknown domain: no row to count on, so count the bounce history
        // itself. Creating an `allowed` row here would put a bouncing domain
        // on the allowed list, and creating a `blocked` one on first strike
        // would jump the threshold — so the row only appears once the
        // evidence is complete.
        $observed = EmailBounce::query()
            ->where('email', 'like', '%@'.$domain)
            ->whereIn('type', [BounceType::Hard, BounceType::Complaint])
            ->count();

        if ($observed >= $this->blockThreshold()) {
            $this->domains->upsert(new DomainEntry(
                domain: $domain,
                status: DomainStatus::Blocked,
                source: DomainSource::Learned,
                bounces: $observed,
            ));
        }
    }

    public function recordDelivery(string $domain): void
    {
        $entry = $this->domains->find($domain);

        if ($entry === null) {
            // First confirmed delivery: the domain earns its allowed entry
            // (§ 6.4) — the only way a row is ever created by learning.
            $this->domains->upsert(new DomainEntry(
                domain: $domain,
                status: DomainStatus::Allowed,
                source: DomainSource::Learned,
                deliveries: 1,
            ));

            return;
        }

        $this->domains->incrementDeliveries($domain);

        // A delivery after a learned block proves the block wrong; only a
        // LEARNED block, though — a human's `blocked` stays blocked.
        if ($entry->status === DomainStatus::Blocked && $entry->source === DomainSource::Learned) {
            $this->domains->upsert(new DomainEntry(
                domain: $domain,
                status: DomainStatus::Allowed,
                source: DomainSource::Learned,
                note: $entry->note,
            ));
        }
    }

    private function maybeBlock(?DomainEntry $entry): void
    {
        if ($entry === null
            || $entry->source === DomainSource::Manual
            || $entry->status === DomainStatus::Blocked
            || $entry->deliveries > 0
            || $entry->bounces < $this->blockThreshold()) {
            return;
        }

        $this->domains->upsert(new DomainEntry(
            domain: $entry->domain,
            status: DomainStatus::Blocked,
            correctedDomain: null,
            source: DomainSource::Learned,
            note: $entry->note,
        ));
    }

    private function blockThreshold(): int
    {
        return (int) config('arkhe-email.learning.block_threshold', 3);
    }
}
