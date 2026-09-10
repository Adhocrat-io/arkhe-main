<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\DTOs;

use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use InvalidArgumentException;

/**
 * One row of domain knowledge, validated at construction so nothing
 * downstream ever meets a `typo` without its correction (FRD § 7.1).
 */
final readonly class DomainEntry
{
    public string $domain;

    public ?string $correctedDomain;

    public function __construct(
        string $domain,
        public DomainStatus $status,
        ?string $correctedDomain = null,
        public DomainSource $source = DomainSource::Manual,
        public int $bounces = 0,
        public int $deliveries = 0,
        public ?string $note = null,
    ) {
        $this->domain = self::normalizeDomain($domain);
        $this->correctedDomain = $correctedDomain === null ? null : self::normalizeDomain($correctedDomain);

        if ($this->domain === '') {
            throw new InvalidArgumentException('A domain entry needs a domain.');
        }

        if ($this->status === DomainStatus::Typo && $this->correctedDomain === null) {
            throw new InvalidArgumentException(
                "Typo entry '{$this->domain}' has no corrected_domain — a typo without its correction is unusable."
            );
        }
    }

    /**
     * The identity used to compare an entry against the seed for delta
     * exports: everything shareable, nothing local (FRD § 7.2).
     */
    public function fingerprint(): string
    {
        return $this->domain.'|'.$this->status->value.'|'.($this->correctedDomain ?? '');
    }

    /**
     * Lowercase and IDNA-ASCII (`café.fr` → `xn--caf-dma.fr`), matching the
     * unique column's contract (FRD § 6.1). Skipped gracefully when ext-intl
     * is absent: a non-ASCII domain then stays as typed, which only costs a
     * missed lookup, never a wrong verdict.
     */
    public static function normalizeDomain(string $domain): string
    {
        $domain = mb_strtolower(trim($domain));

        if ($domain !== '' && ! preg_match('/^[a-z0-9.-]+$/', $domain) && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii)) {
                return $ascii;
            }
        }

        return $domain;
    }
}
