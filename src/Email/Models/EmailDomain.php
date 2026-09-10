<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Models;

use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin on purpose — relations and casts only, Arkhe's model rule. All logic
 * lives in the repository and services (FRD § 4).
 */
class EmailDomain extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('arkhe-email.tables.domains', 'arkhe_email_domains');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'source' => DomainSource::class,
            'bounces' => 'integer',
            'deliveries' => 'integer',
        ];
    }
}
