<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\DTOs\ImportReport;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Services\DomainListImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Imports an exchange file with the § 7.3 merge rules, or — `--from-php` —
 * converts a legacy hard-coded domain list into `manual` table entries so
 * an application migrating to the module does not lose what it already
 * knew (FRD § 14.5): an associative array maps typos to their corrections,
 * a plain list becomes allowed domains.
 */
class ImportEmailDomainsCommand extends Command
{
    protected $signature = 'arkhe:email:import-domains
                            {file? : Exchange JSON file to import}
                            {--dry-run : Report what would happen without writing}
                            {--from-php= : Convert a PHP file returning a domain array into manual entries}';

    protected $description = 'Import an email domain exchange file (or convert a legacy PHP list)';

    public function handle(DomainListImportService $importer, EmailDomainRepository $domains): int
    {
        $fromPhp = $this->option('from-php');

        if (is_string($fromPhp) && $fromPhp !== '') {
            return $this->importFromPhp($fromPhp, $domains);
        }

        $file = $this->argument('file');

        if (! is_string($file) || ! file_exists($file)) {
            $this->error('Give me an exchange file to import, or --from-php=<path>.');

            return self::FAILURE;
        }

        try {
            $document = DomainListDocument::fromJson((string) file_get_contents($file));
        } catch (InvalidArgumentException $e) {
            $this->error("File rejected: {$e->getMessage()}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $importer->import($document, dryRun: $dryRun);

        $this->renderReport($report, $dryRun);

        return self::SUCCESS;
    }

    private function importFromPhp(string $path, EmailDomainRepository $domains): int
    {
        if (! file_exists($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        $data = require $path;

        if (! is_array($data)) {
            $this->error('The PHP file must return an array (typo => correction, or a plain list of domains).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $written = 0;

        foreach ($data as $key => $value) {
            // Associative row = a typo and its correction; numeric row = an
            // allowed domain. `manual` on purpose: converting a legacy list
            // is a human act, and manual is what nothing automated touches.
            $entry = is_string($key)
                ? new DomainEntry($key, DomainStatus::Typo, correctedDomain: (string) $value, source: DomainSource::Manual)
                : new DomainEntry((string) $value, DomainStatus::Allowed, source: DomainSource::Manual);

            if (! $dryRun) {
                $domains->upsert($entry);
            }

            $written++;

            $this->line(sprintf(
                '  %s %s%s',
                $entry->status === DomainStatus::Typo ? 'typo   ' : 'allowed',
                $entry->domain,
                $entry->correctedDomain !== null ? ' → '.$entry->correctedDomain : '',
            ));
        }

        $this->info($dryRun
            ? "{$written} entries would be written as manual (dry-run, nothing touched)."
            : "{$written} manual entries written.");

        return self::SUCCESS;
    }

    private function renderReport(ImportReport $report, bool $dryRun): void
    {
        $this->table(['Created', 'Updated', 'Unchanged', 'Conflicts'], [[
            $report->created,
            $report->updated,
            $report->unchanged,
            count($report->conflicts),
        ]]);

        if ($report->conflicts !== []) {
            $this->warn('Conflicts (kept as-is — local knowledge outranks the file):');
            $this->table(
                ['Domain', 'Local status', 'Local source', 'File wanted'],
                array_map(fn (array $c): array => [$c['domain'], $c['localStatus'], $c['localSource'], $c['fileStatus']], $report->conflicts),
            );
        }

        if ($dryRun) {
            $this->info('Dry-run: nothing was written.');
        }
    }
}
