<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Repositories;

use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Models\EmailBounce;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class EloquentEmailBounceRepository implements EmailBounceRepository
{
    public function record(string $email, BounceType $type, string $provider, ?string $reason, CarbonImmutable $at): void
    {
        EmailBounce::query()->create([
            'email' => mb_strtolower(trim($email)),
            'type' => $type,
            'provider' => $provider,
            'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
            'bounced_at' => $at,
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    public function isSuppressed(string $email): bool
    {
        if ($this->hasActiveHardBounce($email)) {
            return true;
        }

        // "Consecutive" soft bounces are the uncleared ones: rehabilitation
        // stamps them, and a hard verdict would have suppressed on its own —
        // so an uncleared run only ever means repeated soft failures.
        $threshold = (int) config('arkhe-email.bounces.soft_to_hard_threshold', 5);

        return $this->activeQuery($email)
            ->where('type', BounceType::Soft)
            ->count() >= $threshold;
    }

    public function hasActiveHardBounce(string $email): bool
    {
        return $this->activeQuery($email)
            ->whereIn('type', [BounceType::Hard, BounceType::Complaint])
            ->exists();
    }

    public function clear(string $email): void
    {
        $this->activeQuery($email)->update(['cleared_at' => CarbonImmutable::now()]);
    }

    private function activeQuery(string $email): Builder
    {
        return EmailBounce::query()
            ->where('email', mb_strtolower(trim($email)))
            ->whereNull('cleared_at');
    }
}
