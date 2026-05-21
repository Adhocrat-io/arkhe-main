<?php

declare(strict_types=1);

use Arkhe\Main\Support\Features;

it('returns true by default for cookie consent (first-class since 3.1.0)', function (): void {
    expect(Features::hasCookieConsent())->toBeTrue();
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
