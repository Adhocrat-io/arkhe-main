<?php

declare(strict_types=1);

use Arkhe\Main\Support\Features;

it('returns false by default for cookie consent', function (): void {
    expect(Features::hasCookieConsent())->toBeFalse();
});

it('returns true by default for seo (first-class since 3.1.0)', function (): void {
    expect(Features::hasSeo())->toBeTrue();
});

it('flips with config overrides', function (): void {
    config()->set('arkhe.features.cookie_consent', true);
    config()->set('arkhe.features.seo', false);

    expect(Features::hasCookieConsent())->toBeTrue();
    expect(Features::hasSeo())->toBeFalse();
});
