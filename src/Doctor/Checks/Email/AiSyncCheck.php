<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Ai\AiFileSync;
use Arkhe\Main\Doctor\DoctorResult;

/**
 * `ai.sync` (FR-AI-07): after a composer update, the assistant files may
 * lag the package's stubs — a warning, since a stale CLAUDE.md misleads
 * without breaking anything.
 */
class AiSyncCheck extends EmailCheck
{
    public function __construct(private readonly AiFileSync $sync) {}

    public function id(): string
    {
        return 'ai.sync';
    }

    public function module(): string
    {
        return 'ai';
    }

    protected function execute(): DoctorResult
    {
        $stale = $this->sync->stale();

        if ($stale !== []) {
            return $this->warning(
                'Fichiers assistant désynchronisés : '.implode(', ', $stale).'.',
                'Lancer `php artisan arkhe:sync-ai`.',
                targets: $stale,
            );
        }

        return $this->ok('Fichiers assistant à jour (CLAUDE.md, AGENTS.md, rules, skill).');
    }
}
