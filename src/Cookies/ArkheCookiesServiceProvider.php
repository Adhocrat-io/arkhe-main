<?php

declare(strict_types=1);

namespace Arkhe\Main\Cookies;

use Arkhe\Main\Support\Features;
use Whitecube\LaravelCookieConsent\CookiesServiceProvider;
use Whitecube\LaravelCookieConsent\Facades\Cookies;

/**
 * Arkhe-shipped defaults for whitecube/laravel-cookie-consent. Pre-registers
 * Laravel's session + CSRF cookies as essentials so the consent banner works
 * out of the box. Consumers can either:
 *
 * 1. Leave this provider as-is — get the GDPR-compliant baseline for free.
 * 2. Publish the upstream stub (`vendor:publish --tag=laravel-cookie-consent-service-provider`)
 *    and either replace this one or register their own on top — Whitecube
 *    invokes every CookiesServiceProvider subclass that's been registered.
 *
 * Disable the integration entirely by setting `arkhe.features.cookie_consent`
 * to false; the package's blade directives in the Arkhe layout then no-op.
 */
class ArkheCookiesServiceProvider extends CookiesServiceProvider
{
    protected function registerCookies(): void
    {
        if (! Features::hasCookieConsent()) {
            return;
        }

        Cookies::essentials()
            ->session()
            ->csrf();
    }
}
