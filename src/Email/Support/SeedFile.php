<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Support;

use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use InvalidArgumentException;

/**
 * Locates and reads the shipped seed — one resolution shared by the seed
 * command, the delta export (which diffs against it) and the doctor check,
 * so "the seed" can never mean three different files.
 *
 * Resolution order: explicit `arkhe-email.domains.seed_path`, then the copy
 * the application published, then the file shipped inside the package.
 */
final class SeedFile
{
    public function path(): ?string
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

    public function document(): ?DomainListDocument
    {
        $path = $this->path();

        if ($path === null) {
            return null;
        }

        try {
            return DomainListDocument::fromJson((string) file_get_contents($path));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The seed entries, or an empty list when no seed resolves — a delta
     * against nothing is simply the full list, which is the honest answer
     * for an app that never had a seed.
     *
     * @return list<DomainEntry>
     */
    public function entries(): array
    {
        return $this->document()?->entries ?? [];
    }
}
