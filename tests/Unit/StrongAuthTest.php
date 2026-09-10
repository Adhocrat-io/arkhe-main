<?php

declare(strict_types=1);

use Arkhe\Main\Support\StrongAuth;
use Arkhe\Main\Tests\Stubs\User;
use Arkhe\Main\Tests\Stubs\UserWithoutStrongAuth;

/**
 * Register a named route and make it visible to `Route::has()`.
 *
 * The router answers name lookups from a table built when the collection is
 * refreshed, so a route added mid-test stays invisible until we rebuild it.
 */
function registerNamedRoute(string $name, string $uri): void
{
    app('router')->get($uri, fn () => $name)->name($name);
    app('router')->getRoutes()->refreshNameLookups();
}

// ─── enabled() ───────────────────────────────────────────────────────────

// The shipped default, read the way a fresh install would — straight from the
// config file, bypassing the suite's baseline override in TestCase. This is
// the headline requirement: 2FA is mandatory unless the app opts out.
it('ships with enforcement on', function (): void {
    $config = require dirname(__DIR__, 2).'/config/arkhe.php';

    expect($config['strong_auth']['enforce'])->toBeTrue();
});

// Laravel's env parser turns `ARKHE_STRONG_AUTH=false` into a real boolean, but
// a published config or a quoted .env value arrives as a string. Both spellings
// have to mean the same thing.
it('stands down on any spelling of no', function (mixed $value): void {
    config()->set('arkhe.strong_auth.enforce', $value);

    expect(StrongAuth::enabled())->toBeFalse();
})->with([false, 0, '0', 'false', 'FALSE', ' false ', 'off', 'no']);

// Enforcement is the default, so only an explicit "no" may drop it. A typo
// dropping a security gate would be silent; a typo keeping it up is loudly
// visible and the interstitial is a minute from resolved.
it('keeps enforcing on anything else', function (mixed $value): void {
    config()->set('arkhe.strong_auth.enforce', $value);

    expect(StrongAuth::enabled())->toBeTrue();
})->with([true, null, 1, '1', 'true', 'on', 'yes', '', 'backend', 'root', 'maybe']);

it('enforces when the key is absent entirely', function (): void {
    config()->set('arkhe.strong_auth', null);

    expect(StrongAuth::enabled())->toBeTrue();
});

// ─── isSupportedBy() ─────────────────────────────────────────────────────

it('detects a model exposing the probe methods', function (): void {
    expect(StrongAuth::isSupportedBy(new User))->toBeTrue();
});

it('detects a model exposing neither', function (): void {
    expect(StrongAuth::isSupportedBy(new UserWithoutStrongAuth))->toBeFalse();
});

it('treats a null user as unsupported', function (): void {
    expect(StrongAuth::isSupportedBy(null))->toBeFalse();
});

// ─── enrolmentRoute() ────────────────────────────────────────────────────

it('prefers an explicitly configured route', function (): void {
    registerNamedRoute('custom.security', '/x');
    registerNamedRoute('security.edit', '/y');
    config()->set('arkhe.strong_auth.route', 'custom.security');

    expect(StrongAuth::enrolmentRoute())->toBe('custom.security');
});

// A stale name in config must not produce a broken redirect: better to fall
// through to the diagnostic notice, which says what to fix.
it('ignores a configured route that does not resolve', function (): void {
    config()->set('arkhe.strong_auth.route', 'gone.away');

    expect(StrongAuth::enrolmentRoute())->toBeNull();
});

it('probes the conventional names when unconfigured', function (): void {
    registerNamedRoute('security.edit', '/settings/security');

    expect(StrongAuth::enrolmentRoute())->toBe('security.edit');
});

it('falls back to the Jetstream-era name', function (): void {
    registerNamedRoute('two-factor.show', '/user/two-factor');

    expect(StrongAuth::enrolmentRoute())->toBe('two-factor.show');
});

// The profile page carries no 2FA or passkey controls in current starter kits,
// so it is deliberately absent from the probe list: redirecting there would
// strand the user somewhere they cannot enrol.
it('does not fall back to a profile page', function (): void {
    registerNamedRoute('profile.edit', '/settings/profile');
    registerNamedRoute('profile.show', '/user/profile');

    expect(StrongAuth::enrolmentRoute())->toBeNull();
});

it('returns null when nothing resolves', function (): void {
    expect(StrongAuth::enrolmentRoute())->toBeNull();
});

// ─── enrolmentUrl() ──────────────────────────────────────────────────────

it('generates the URL of the resolved route', function (): void {
    registerNamedRoute('security.edit', '/settings/security');

    expect(StrongAuth::enrolmentUrl())->toBe(route('security.edit'));
});

// `Route::has()` passes for a route that exists but takes a segment, and
// `route()` then throws. Unguarded that threw inside the interstitial, so the
// one page explaining the block answered 500 and the blocked admin had no way
// back — the very lockout the page exists to prevent.
it('returns null rather than throwing on a parameterised route', function (): void {
    app('router')->get('/settings/{tenant}/security', fn () => 'x')->name('security.edit');
    app('router')->getRoutes()->refreshNameLookups();

    expect(StrongAuth::enrolmentUrl())->toBeNull();
});

it('returns null when no route resolves at all', function (): void {
    expect(StrongAuth::enrolmentUrl())->toBeNull();
});

// ─── satisfiedBy() ───────────────────────────────────────────────────────

it('refuses a null user', function (): void {
    expect(StrongAuth::satisfiedBy(null))->toBeFalse();
});

it('refuses a model that cannot answer either probe', function (): void {
    expect(StrongAuth::satisfiedBy(new UserWithoutStrongAuth))->toBeFalse();
});

// A probe that throws must not be what grants access. The passkeys table can
// be missing mid-install while the trait is already on the model, and every
// backend page would otherwise 500 from inside middleware.
it('treats a throwing probe as an absent factor', function (): void {
    $user = new class extends User
    {
        public function hasEnabledTwoFactorAuthentication(): bool
        {
            throw new RuntimeException('boom');
        }

        public function hasPasskeysEnabled(): bool
        {
            throw new RuntimeException('no such table: passkeys');
        }
    };

    expect(StrongAuth::satisfiedBy($user))->toBeFalse();
});
