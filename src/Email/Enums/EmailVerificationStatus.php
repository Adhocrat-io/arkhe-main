<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Enums;

/**
 * Verdict of a verification — from a single driver or from the whole pipeline.
 *
 * The scale is deliberately asymmetric: `Deliverable` requires positive proof
 * (a domain we know accepts mail), while "nothing wrong found" is only ever
 * `Unverifiable`. A technical failure (DNS unreachable, cache down) must also
 * answer `Unverifiable` — never a definitive verdict in either direction.
 */
enum EmailVerificationStatus: string
{
    case Deliverable = 'deliverable';

    case Risky = 'risky';

    case Undeliverable = 'undeliverable';

    case Unverifiable = 'unverifiable';

    /**
     * Only `Undeliverable` may ever fail a validation. `Risky` is a UI signal
     * — "did you mean…?" — and blocking on a guess would reject legitimate
     * addresses, which the module must never do.
     */
    public function blocks(): bool
    {
        return $this === self::Undeliverable;
    }

    public function needsConfirmation(): bool
    {
        return $this === self::Risky;
    }
}
