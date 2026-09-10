<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Support;

use Arkhe\Main\Email\Contracts\DnsResolver;
use Throwable;

/**
 * PHP's own resolver. No hard timeout control exists at this level —
 * dns_get_record honours the system resolver's timeouts — which is one
 * more reason the MX driver ships disabled and cached (FRD § 5.8).
 */
final class NativeDnsResolver implements DnsResolver
{
    public function has(string $domain, string $type): ?bool
    {
        $constant = match ($type) {
            'MX' => DNS_MX,
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            default => null,
        };

        if ($constant === null || $domain === '') {
            return null;
        }

        // The trailing dot makes the name absolute, so the lookup can never
        // wander through resolv.conf search domains and "find" a record on
        // intranet.example.corp.
        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($domain.'.', $constant);
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        return $records === false ? null : $records !== [];
    }
}
