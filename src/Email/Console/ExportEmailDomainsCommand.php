<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Arkhe\Main\Email\Services\DomainListExportService;
use Illuminate\Console\Command;

/**
 * Writes the exchange file (FRD § 7.2). `--delta` exports only what this
 * application changed or learned relative to the shipped seed — the useful
 * payload for consolidation. Without `--output` the JSON goes to stdout, so
 * the file can be piped or redirected by whatever channel the client uses.
 */
class ExportEmailDomainsCommand extends Command
{
    protected $signature = 'arkhe:email:export-domains
                            {--delta : Export only entries differing from the shipped seed}
                            {--with-notes : Include admin notes in the file}
                            {--output= : Write to this path instead of stdout}';

    protected $description = 'Export the email domain list as an exchange JSON file';

    public function handle(DomainListExportService $exporter): int
    {
        $document = $exporter->export(
            kind: $this->option('delta') ? 'delta' : 'full',
            withNotes: (bool) $this->option('with-notes'),
        );

        $json = $document->toJson(withNotes: (bool) $this->option('with-notes'));

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $json.PHP_EOL);
            $this->info(sprintf('%d entries (%s) written to %s.', count($document->entries), $document->kind, $output));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
