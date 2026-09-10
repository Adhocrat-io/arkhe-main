<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\DTOs;

/**
 * An address to verify, together with what the form knows about its owner.
 *
 * Normalisation happens once, here: every driver downstream reads
 * `$localPart` and `$domain` already lowercased and trimmed, so none of them
 * re-implements the split and none can disagree about it.
 */
final readonly class EmailCandidate
{
    public string $localPart;

    public string $domain;

    public function __construct(
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
    ) {
        $normalized = mb_strtolower(trim($email));

        // strrpos, not strpos: RFC 5321 allows quoted local parts containing
        // `@`; the domain is always what follows the LAST separator.
        $at = strrpos($normalized, '@');

        $this->localPart = $at === false ? $normalized : substr($normalized, 0, $at);
        $this->domain = $at === false ? '' : substr($normalized, $at + 1);
    }

    public function hasNames(): bool
    {
        return trim((string) $this->firstName) !== '' || trim((string) $this->lastName) !== '';
    }

    /**
     * The full address with the local part replaced — how a driver builds a
     * suggestion when it corrects the part before the `@`.
     */
    public function withLocalPart(string $localPart): string
    {
        return $localPart.'@'.$this->domain;
    }

    /**
     * The full address with the domain replaced — how a driver builds a
     * suggestion when it corrects a typo'd domain.
     */
    public function withDomain(string $domain): string
    {
        return $this->localPart.'@'.$domain;
    }
}
