<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\DTOs;

/**
 * What an import did — or would do, in dry-run (FRD § 7.3). Conflicts are
 * the rows the merge rules protected (`manual` always, `learned` when the
 * file disagrees with local field data): listed, never silently dropped,
 * so the operator confirms with full knowledge.
 */
final readonly class ImportReport
{
    /**
     * @param  list<array{domain: string, localStatus: string, localSource: string, fileStatus: string}>  $conflicts
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public array $conflicts = [],
    ) {}

    public function changes(): int
    {
        return $this->created + $this->updated;
    }

    /**
     * @return array{created: int, updated: int, unchanged: int, conflicts: list<array{domain: string, localStatus: string, localSource: string, fileStatus: string}>}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'conflicts' => $this->conflicts,
        ];
    }
}
