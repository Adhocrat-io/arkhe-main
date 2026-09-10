<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Services;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\Support\SeedFile;
use Carbon\CarbonImmutable;

/**
 * Builds the exchange document (FRD § 7.2). `delta` is the interesting kind:
 * only what this application learned or changed relative to the shipped
 * seed — which is exactly the knowledge worth sending back for
 * consolidation, and nothing identifying unless `export.origin` was set.
 */
final class DomainListExportService
{
    public function __construct(
        private readonly EmailDomainRepository $domains,
        private readonly SeedFile $seed,
    ) {}

    /**
     * @param  'full'|'delta'  $kind
     */
    public function export(string $kind = 'full', bool $withNotes = false): DomainListDocument
    {
        $entries = $kind === 'delta'
            ? $this->domains->delta($this->seed->entries())
            : $this->domains->all();

        $origin = config('arkhe-email.export.origin');

        return new DomainListDocument(
            kind: $kind,
            entries: array_values(iterator_to_array($entries, preserve_keys: false)),
            origin: is_string($origin) && $origin !== '' ? $origin : null,
            exportedAt: CarbonImmutable::now(),
        );
    }
}
