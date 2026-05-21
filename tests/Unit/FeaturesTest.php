<?php

declare(strict_types=1);

use Arkhe\Main\Support\Features;

it('returns false by default for cookie consent', function (): void {
    expect(Features::hasCookieConsent())->toBeFalse();
});

it('returns false by default for seo', function (): void {
    expect(Features::hasSeo())->toBeFalse();
});

it('flips with config overrides', function (): void {
    config()->set('arkhe.features.cookie_consent', true);
    config()->set('arkhe.features.seo', true);

    expect(Features::hasCookieConsent())->toBeTrue();
    expect(Features::hasSeo())->toBeTrue();
});
