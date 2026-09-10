<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Services;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\DTOs\ImportReport;
use Arkhe\Main\Email\Enums\DomainSource;
use Illuminate\Support\Facades\DB;

/**
 * Merges an exchange file into the local table (FRD § 7.3). The rules
 * encode one principle: knowledge closest to the ground wins. `manual` (a
 * human decided) and a disagreeing `learned` (this app's own bounces said
 * otherwise) are never overwritten by a file — they surface as conflicts
 * for a human to arbitrate instead.
 *
 * Idempotent by construction — replaying a file finds every entry already
 * in its target state — and transactional: either the whole file lands, or
 * none of it does.
 */
final class DomainListImportService
{
    public function __construct(private readonly EmailDomainRepository $domains) {}

    public function import(DomainListDocument $document, bool $dryRun = false): ImportReport
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $conflicts = [];

        /** @var list<DomainEntry> $writes */
        $writes = [];

        foreach ($document->entries as $entry) {
            $local = $this->domains->find($entry->domain);

            if ($local === null) {
                $created++;
                $writes[] = $this->asImported($entry, note: $entry->note);

                continue;
            }

            if ($local->source === DomainSource::Manual) {
                $conflicts[] = $this->conflict($local, $entry);

                continue;
            }

            if ($local->source === DomainSource::Learned) {
                // Same status: nothing to do. Different: the file loses —
                // local field data primes over a central list (FRD § 7.3).
                if ($local->status === $entry->status) {
                    $unchanged++;
                } else {
                    $conflicts[] = $this->conflict($local, $entry);
                }

                continue;
            }

            // seed / imported: freely updatable.
            $note = $entry->note ?? $local->note;

            if ($local->fingerprint() === $entry->fingerprint() && $note === $local->note) {
                $unchanged++;

                continue;
            }

            $updated++;
            $writes[] = $this->asImported($entry, note: $note);
        }

        if (! $dryRun && $writes !== []) {
            DB::transaction(function () use ($writes): void {
                foreach ($writes as $entry) {
                    $this->domains->upsert($entry);
                }
            });
        }

        return new ImportReport($created, $updated, $unchanged, $conflicts);
    }

    private function asImported(DomainEntry $entry, ?string $note): DomainEntry
    {
        return new DomainEntry(
            domain: $entry->domain,
            status: $entry->status,
            correctedDomain: $entry->correctedDomain,
            source: DomainSource::Imported,
            note: $note,
        );
    }

    /**
     * @return array{domain: string, localStatus: string, localSource: string, fileStatus: string}
     */
    private function conflict(DomainEntry $local, DomainEntry $file): array
    {
        return [
            'domain' => $local->domain,
            'localStatus' => $local->status->value,
            'localSource' => $local->source->value,
            'fileStatus' => $file->status->value,
        ];
    }
}
