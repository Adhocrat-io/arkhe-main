<?php

declare(strict_types=1);

namespace Adhocrat\Arkhe\Concerns;

use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

/**
 * The HasBackendProfile trait wires up the Arkhe profile attributes (full name,
 * avatar, initials) and embeds Spatie's HasRoles trait. Consumers MUST NOT
 * `use HasRoles` separately on the same model — this trait already exposes it.
 */
trait HasBackendProfile
{
    use HasRoles;

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar_path)) {
            return null;
        }

        $disk = config('arkhe.avatar_disk', 'public');

        return Storage::disk($disk)->url($this->avatar_path);
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_substr((string) ($this->first_name ?? ''), 0, 1);
        $last  = mb_substr((string) ($this->last_name ?? ''), 0, 1);

        return mb_strtoupper($first.$last);
    }

    public function isArkheRoot(): bool
    {
        return $this->hasRole((string) config('arkhe.roles.root'));
    }

    public function isArkheAdmin(): bool
    {
        return $this->hasAnyRole([
            (string) config('arkhe.roles.root'),
            (string) config('arkhe.roles.administrator'),
        ]);
    }
}
