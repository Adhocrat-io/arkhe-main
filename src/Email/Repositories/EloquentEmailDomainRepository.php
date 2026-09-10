<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Repositories;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Models\EmailDomain;
use Arkhe\Main\Email\Support\EmailModule;
use Illuminate\Support\Facades\Cache;

/**
 * The cache lives here and nowhere else (FRD § 6.2): `find()` runs inside
 * the validation pipeline of every email field, so it must not cost a query
 * per keystroke — but a stale verdict is worse than a slow one, so every
 * write through this repository forgets the entry it touched.
 */
class EloquentEmailDomainRepository implements EmailDomainRepository
{
    /**
     * Negative results are cached too — most domains are simply absent from
     * the table, and re-asking on every request would defeat the cache where
     * it matters most. The sentinel distinguishes "cached absent" from
     * "not cached".
     */
    private const ABSENT = 'absent';

    public function find(string $domain): ?DomainEntry
    {
        $domain = DomainEntry::normalizeDomain($domain);

        if ($domain === '') {
            return null;
        }

        $cached = Cache::remember(
            $this->cacheKey($domain),
            (int) config('arkhe-email.domains.cache_ttl', 3600),
            function () use ($domain): array|string {
                $model = EmailDomain::query()->where('domain', $domain)->first();

                return $model === null ? self::ABSENT : $this->toArray($model);
            },
        );

        return $cached === self::ABSENT ? null : $this->fromArray($cached);
    }

    public function upsert(DomainEntry $entry): void
    {
        $model = EmailDomain::query()->firstOrNew(['domain' => $entry->domain]);

        $model->fill([
            'status' => $entry->status,
            'corrected_domain' => $entry->correctedDomain,
            'source' => $entry->source,
            'note' => $entry->note,
        ]);

        // Counters only seed a NEW row; on update they belong to the
        // increment methods — an import rewriting a status must never reset
        // what the field observed locally.
        if (! $model->exists) {
            $model->bounces = $entry->bounces;
            $model->deliveries = $entry->deliveries;
        }

        $model->save();

        Cache::forget($this->cacheKey($entry->domain));
    }

    public function all(): iterable
    {
        foreach (EmailDomain::query()->orderBy('domain')->lazy() as $model) {
            yield $this->fromArray($this->toArray($model));
        }
    }

    public function delta(iterable $seed): iterable
    {
        $known = [];

        foreach ($seed as $entry) {
            $known[$entry->domain] = $entry->fingerprint();
        }

        foreach ($this->all() as $entry) {
            if (($known[$entry->domain] ?? null) !== $entry->fingerprint()) {
                yield $entry;
            }
        }
    }

    public function incrementBounces(string $domain): void
    {
        $this->increment($domain, 'bounces');
    }

    public function incrementDeliveries(string $domain): void
    {
        $this->increment($domain, 'deliveries');
    }

    private function increment(string $domain, string $column): void
    {
        $domain = DomainEntry::normalizeDomain($domain);

        EmailDomain::query()->where('domain', $domain)->increment($column);

        Cache::forget($this->cacheKey($domain));
    }

    private function cacheKey(string $domain): string
    {
        return EmailModule::cacheKey('domain:'.$domain);
    }

    /**
     * The cache stores scalar arrays, not models or DTOs: a serialized model
     * would drag connection state around, and a DTO's shape change on
     * package update would poison every warm cache in production.
     *
     * @return array{domain: string, status: string, corrected_domain: ?string, source: string, bounces: int, deliveries: int, note: ?string}
     */
    private function toArray(EmailDomain $model): array
    {
        return [
            'domain' => (string) $model->domain,
            'status' => $model->status->value,
            'corrected_domain' => $model->corrected_domain,
            'source' => $model->source->value,
            'bounces' => (int) $model->bounces,
            'deliveries' => (int) $model->deliveries,
            'note' => $model->note,
        ];
    }

    /**
     * @param  array{domain: string, status: string, corrected_domain: ?string, source: string, bounces: int, deliveries: int, note: ?string}  $row
     */
    private function fromArray(array $row): DomainEntry
    {
        return new DomainEntry(
            domain: $row['domain'],
            status: DomainStatus::from($row['status']),
            correctedDomain: $row['corrected_domain'],
            source: DomainSource::from($row['source']),
            bounces: $row['bounces'],
            deliveries: $row['deliveries'],
            note: $row['note'],
        );
    }
}
