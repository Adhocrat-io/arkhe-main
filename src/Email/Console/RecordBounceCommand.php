<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Manual entry for bounces no webhook will ever bring — the NDR forwarded
 * by a client, the address a phone call proved dead (FRD § 8.2).
 */
class RecordBounceCommand extends Command
{
    protected $signature = 'arkhe:email:record-bounce
                            {email : The address that bounced}
                            {--type=hard : hard, soft or complaint}
                            {--reason= : Optional diagnostic to keep with the record}';

    protected $description = 'Record a bounce by hand (suppresses the address if hard)';

    public function handle(BounceIngestionService $ingestion): int
    {
        $type = BounceType::tryFrom((string) $this->option('type'));

        if ($type === null) {
            $this->error('Unknown type — use hard, soft or complaint.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');

        $ingestion->ingest(
            email: $email,
            type: $type,
            provider: 'manual',
            reason: is_string($this->option('reason')) ? $this->option('reason') : null,
            at: CarbonImmutable::now(),
        );

        $this->info("Bounce recorded ({$type->value}).");

        return self::SUCCESS;
    }
}
