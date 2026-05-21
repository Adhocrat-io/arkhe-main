<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

/**
 * Feature flag container. Methods are assertions (booleans) prefixed `has`.
 * Phase 2 features (cookie consent, SEO) read from `config('arkhe.features.*')`
 * and remain inert while their flags are false.
 */
final class Features
{
    public static function hasCookieConsent(): bool
    {
        return (bool) config('arkhe.features.cookie_consent', false);
    }

    public static function hasSeo(): bool
    {
        return (bool) config('arkhe.features.seo', false);
    }
}
