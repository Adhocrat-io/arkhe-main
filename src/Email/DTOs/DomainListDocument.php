<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\DTOs;

use Arkhe\Main\Email\Enums\DomainStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonException;

/**
 * The exchange file (FRD § 7.1) — the ONLY thing that ever travels between
 * an application and Adhocrat, by whatever channel the client chooses. It
 * carries domains and their status, and deliberately nothing else: no
 * address, no counter, no personal data, no application identifier unless
 * `arkhe-email.export.origin` is explicitly set.
 *
 * Validated at construction: a file with an unknown schema or a single
 * invalid entry is rejected whole, because half-importing a list would
 * leave nobody able to say what the table now reflects.
 */
final readonly class DomainListDocument
{
    public const SCHEMA = 1;

    public const KINDS = ['full', 'delta'];

    /**
     * @param  list<DomainEntry>  $entries  entry `source` is meaningless here —
     *                                      files carry none; consumers assign their own.
     */
    public function __construct(
        public string $kind,
        public array $entries,
        public ?string $origin = null,
        public ?CarbonImmutable $exportedAt = null,
        public int $schema = self::SCHEMA,
    ) {
        if ($this->schema !== self::SCHEMA) {
            throw new InvalidArgumentException("Unknown schema '{$this->schema}' — this Arkhe version reads schema ".self::SCHEMA.' only.');
        }

        if (! in_array($this->kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown kind '{$this->kind}' — expected 'full' or 'delta'.");
        }
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Not a valid JSON document: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($data) || ! is_array($data['entries'] ?? null)) {
            throw new InvalidArgumentException("The document has no 'entries' list.");
        }

        $entries = [];

        foreach ($data['entries'] as $index => $raw) {
            if (! is_array($raw) || ! is_string($raw['domain'] ?? null)) {
                throw new InvalidArgumentException("Entry #{$index} has no domain.");
            }

            $status = is_string($raw['status'] ?? null) ? DomainStatus::tryFrom($raw['status']) : null;

            if ($status === null) {
                throw new InvalidArgumentException("Entry '{$raw['domain']}' has an unknown status '".(string) ($raw['status'] ?? '')."'.");
            }

            // DomainEntry's constructor enforces the typo/corrected_domain
            // invariant — one bad entry rejects the whole file (FRD § 7.1).
            $entries[] = new DomainEntry(
                domain: $raw['domain'],
                status: $status,
                correctedDomain: is_string($raw['corrected_domain'] ?? null) ? $raw['corrected_domain'] : null,
                note: is_string($raw['note'] ?? null) ? $raw['note'] : null,
            );
        }

        return new self(
            kind: is_string($data['kind'] ?? null) ? $data['kind'] : 'full',
            entries: $entries,
            origin: is_string($data['origin'] ?? null) ? $data['origin'] : null,
            exportedAt: is_string($data['exported_at'] ?? null) ? CarbonImmutable::parse($data['exported_at']) : null,
            schema: is_int($data['schema'] ?? null) ? $data['schema'] : -1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withNotes = false): array
    {
        return [
            'schema' => $this->schema,
            'kind' => $this->kind,
            'exported_at' => $this->exportedAt?->toIso8601ZuluString(),
            'origin' => $this->origin,
            'entries' => array_map(function (DomainEntry $entry) use ($withNotes): array {
                $row = ['domain' => $entry->domain, 'status' => $entry->status->value];

                if ($entry->correctedDomain !== null) {
                    $row['corrected_domain'] = $entry->correctedDomain;
                }

                if ($withNotes && $entry->note !== null) {
                    $row['note'] = $entry->note;
                }

                return $row;
            }, $this->entries),
        ];
    }

    public function toJson(bool $withNotes = false): string
    {
        return json_encode($this->toArray($withNotes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
