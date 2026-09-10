<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Models;

use Arkhe\Main\Email\Enums\BounceType;
use Illuminate\Database\Eloquent\Model;

class EmailBounce extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('arkhe-email.tables.bounces', 'arkhe_email_bounces');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BounceType::class,
            'bounced_at' => 'immutable_datetime',
            'cleared_at' => 'immutable_datetime',
        ];
    }
}
