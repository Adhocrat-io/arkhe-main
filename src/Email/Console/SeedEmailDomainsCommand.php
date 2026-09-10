<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\Enums\DomainSource;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Loads the shipped domain list with `source = seed` (FRD § 6.3).
 *
 * Idempotent, and deliberately timid: a row whose source is anything but
 * `seed` is NEVER overwritten — whatever an admin typed, a file brought or
 * the field taught locally outranks the factory list. Wired into the host
 * app's DatabaseSeeder by `arkhe:email:install`.
 */
class SeedEmailDomainsCommand extends Command
{
    protected $signature = 'arkhe:email:seed-domains';

    protected $description = 'Load the shipped email domain list (never overwrites non-seed rows)';

    public function handle(EmailDomainRepository $domains): int
    {
        $path = $this->seedPath();

        if ($path === null) {
            $this->error('No seed file found. Publish it with arkhe:email:install, or set arkhe-email.domains.seed_path.');

            return self::FAILURE;
        }

        try {
            $document = DomainListDocument::fromJson((string) file_get_contents($path));
        } catch (InvalidArgumentException $e) {
            $this->error("Seed file rejected: {$e->getMessage()}");

            return self::FAILURE;
        }

        $written = 0;
        $kept = 0;

        foreach ($document->entries as $entry) {
            $existing = $domains->find($entry->domain);

            if ($existing !== null && $existing->source !== DomainSource::Seed) {
                $kept++;

                continue;
            }

            $domains->upsert(new DomainEntry(
                domain: $entry->domain,
                status: $entry->status,
                correctedDomain: $entry->correctedDomain,
                source: DomainSource::Seed,
                note: $entry->note,
            ));

            $written++;
        }

        $this->info("Seed loaded from {$path}: {$written} written, {$kept} kept (non-seed rows are never overwritten).");

        return self::SUCCESS;
    }

    /**
     * An explicit `seed_path` wins; otherwise the copy the app published,
     * and as a last resort the file shipped inside the package — so a fresh
     * install can seed before publishing anything (FRD § 6.3).
     */
    private function seedPath(): ?string
    {
        $configured = config('arkhe-email.domains.seed_path');

        if (is_string($configured) && $configured !== '') {
            return file_exists($configured) ? $configured : null;
        }

        $published = database_path('seeders/data/email_domains.json');

        if (file_exists($published)) {
            return $published;
        }

        $shipped = dirname(__DIR__, 3).'/database/seeders/data/email_domains.json';

        return file_exists($shipped) ? $shipped : null;
    }
}
