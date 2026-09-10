<?php

declare(strict_types=1);

namespace Arkhe\Main\Ai\Console;

use Arkhe\Main\Ai\AiFileSync;
use Illuminate\Console\Command;

class SyncAiCommand extends Command
{
    protected $signature = 'arkhe:sync-ai
                            {--check : Report out-of-sync files without writing anything}';

    protected $description = 'Publish or refresh the assistant-facing files (CLAUDE.md block, AGENTS.md, rules, skill)';

    public function handle(AiFileSync $sync): int
    {
        if ($this->option('check')) {
            $stale = $sync->stale();

            if ($stale === []) {
                $this->info('Fichiers assistant à jour.');

                return self::SUCCESS;
            }

            $this->warn('Fichiers désynchronisés : '.implode(', ', $stale).' — lancer `php artisan arkhe:sync-ai`.');

            return self::FAILURE;
        }

        $written = $sync->sync();

        $this->info($written === []
            ? 'Fichiers assistant déjà à jour.'
            : 'Fichiers assistant synchronisés : '.implode(', ', $written).'.');

        return self::SUCCESS;
    }
}
