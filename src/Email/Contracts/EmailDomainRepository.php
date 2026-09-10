<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Contracts;

use Arkhe\Main\Email\DTOs\DomainEntry;

/**
 * Access to the `email_domains` knowledge base (FRD § 6.2). The application
 * cache sits behind this interface — callers never see it, and every write
 * through here invalidates the entry it touches.
 */
interface EmailDomainRepository
{
    public function find(string $domain): ?DomainEntry;

    /**
     * Create or update by domain. Local counters (`bounces`, `deliveries`)
     * are preserved on update — they belong to the increment methods below,
     * not to whoever happens to rewrite the status.
     */
    public function upsert(DomainEntry $entry): void;

    /**
     * @return iterable<DomainEntry>
     */
    public function all(): iterable;

    /**
     * Entries whose (domain, status, corrected_domain) differ from the seed,
     * or which the seed does not contain — the shareable knowledge this
     * application produced (FRD § 7.2).
     *
     * @param  iterable<DomainEntry>  $seed
     * @return iterable<DomainEntry>
     */
    public function delta(iterable $seed): iterable;

    public function incrementBounces(string $domain): void;

    public function incrementDeliveries(string $domain): void;

    /**
     * Not in the FRD's § 6.2 interface, required by the § 6.5 admin screen —
     * removal is an admin act, so it lives behind the same cache-invalidating
     * door as every other write.
     */
    public function delete(string $domain): void;
}
