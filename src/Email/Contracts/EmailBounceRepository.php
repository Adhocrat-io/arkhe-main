<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Contracts;

use Arkhe\Main\Email\Enums\BounceType;
use Carbon\CarbonImmutable;

/**
 * The bounce history and the suppression verdict built on it (FRD § 8).
 * `isSuppressed()` is also the module's public answer for the host app's
 * "check your address" banner (§ 8.4).
 */
interface EmailBounceRepository
{
    public function record(string $email, BounceType $type, string $provider, ?string $reason, CarbonImmutable $at): void;

    /**
     * Suppressed = one uncleared hard bounce or complaint, or the configured
     * run of uncleared soft bounces (`arkhe-email.bounces.soft_to_hard_threshold`).
     */
    public function isSuppressed(string $email): bool;

    /**
     * One uncleared hard/complaint on record — the narrower question the
     * BounceHistoryVerifier asks (§ 5.4): soft trouble is not evidence the
     * address does not exist.
     */
    public function hasActiveHardBounce(string $email): bool;

    /**
     * Rehabilitation (§ 8.4): stamps `cleared_at` on every active row so the
     * address counts as clean again while the history stays auditable.
     */
    public function clear(string $email): void;
}
