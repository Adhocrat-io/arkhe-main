<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Enums;

/**
 * What the domain list knows about a domain (FRD § 6.1).
 *
 * `typo` is the interesting one: the domain exists as a string people type
 * (`hotmaij.com`) and the entry carries the domain they meant. Many of those
 * typo domains are squatted with a catch-all MX, which is exactly why an MX
 * record is never taken as proof of deliverability.
 */
enum DomainStatus: string
{
    case Allowed = 'allowed';

    case Blocked = 'blocked';

    case Typo = 'typo';
}
