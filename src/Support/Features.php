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

    /**
     * SEO became a first-class feature in 3.1.0 — always on. The flag remains
     * for backward compatibility with V3.0.x published configs and to let
     * consumers programmatically detect the integration.
     */
    public static function hasSeo(): bool
    {
        return (bool) config('arkhe.features.seo', true);
    }
}
