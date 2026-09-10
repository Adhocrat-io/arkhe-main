<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Support;

use Arkhe\Main\Email\Enums\EmailVerificationMode;

/**
 * The two switches every part of the module keys off (FRD § 14.1, § 14.2).
 *
 * `enabled` is the compatibility contract: while it is false — the shipped
 * default — the service provider registers no listener, no route and no
 * behaviour on the base classes, so an application that merely runs
 * `composer update` behaves exactly as before (NFR-09). `mode` then governs
 * how loud the module is once an application has opted in.
 */
final class EmailModule
{
    public static function enabled(): bool
    {
        return (bool) config('arkhe-email.enabled', false);
    }

    public static function mode(): EmailVerificationMode
    {
        return EmailVerificationMode::fromConfig(config('arkhe-email.mode'));
    }

    /**
     * Cache keys are prefixed with the application name so two applications
     * sharing one cache backend on the same server can never read each
     * other's domain knowledge (FRD § 14.5).
     */
    public static function cacheKey(string $suffix): string
    {
        return 'arkhe-email:'.(string) config('app.name', 'laravel').':'.$suffix;
    }
}
