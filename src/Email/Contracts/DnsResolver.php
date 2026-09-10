<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Contracts;

/**
 * The one seam between the MX driver and the network — injectable so tests
 * (and cautious apps) can swap the real resolver for a fake, and so "the
 * driver did no DNS" is provable rather than assumed (FRD § 5.8, § 11).
 */
interface DnsResolver
{
    /**
     * Does the domain hold at least one record of this type?
     * True/false is an answer; null means the lookup itself failed —
     * and a failed lookup is never allowed to become a verdict.
     *
     * @param  'MX'|'A'|'AAAA'  $type
     */
    public function has(string $domain, string $type): ?bool;
}
