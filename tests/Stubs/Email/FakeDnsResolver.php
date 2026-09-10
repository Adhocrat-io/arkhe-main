<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs\Email;

use Arkhe\Main\Email\Contracts\DnsResolver;

/**
 * Scripted resolver: answers from a map and counts every lookup, so the
 * tests can prove both what the driver concluded AND that a disabled or
 * cached driver asked the network nothing (FRD § 11).
 */
final class FakeDnsResolver implements DnsResolver
{
    public int $lookups = 0;

    /**
     * @param  array<string, ?bool>  $answers  keyed "domain/TYPE"; missing key answers false
     */
    public function __construct(public array $answers = []) {}

    public function has(string $domain, string $type): ?bool
    {
        $this->lookups++;

        return array_key_exists("{$domain}/{$type}", $this->answers)
            ? $this->answers["{$domain}/{$type}"]
            : false;
    }
}
