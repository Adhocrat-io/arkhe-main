<?php

declare(strict_types=1);

namespace Arkhe\Main\Support;

use Arkhe\Main\Http\Middleware\EnsureUserHasBackendAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Strong-authentication probing for the backend gate.
 *
 * "Strong" means one second factor: a registered passkey OR a confirmed TOTP.
 * A passkey exempts from TOTP — it is already two-factor (device possession
 * plus biometric or PIN) and, being bound to the domain by the browser, it is
 * phishing-resistant where a TOTP code is not. Asking for both would add
 * friction without adding safety.
 *
 * Everything here is capability-based. The package requires neither Fortify
 * nor laravel/passkeys, and the host app owns its user model, so we probe for
 * methods and never name a vendor class. That is the same reasoning
 * {@see EnsureUserHasBackendAccess} follows when it
 * checks `method_exists($user, 'can')` rather than depending on the permission
 * package outright.
 *
 * Two method names are the whole surface exposed to upstream churn:
 * `hasEnabledTwoFactorAuthentication()`, which Fortify calls internally, and
 * `hasPasskeysEnabled()`, which laravel/passkeys declares on its `PasskeyUser`
 * interface. Both are public contracts, so neither moves without a major.
 */
final class StrongAuth
{
    /**
     * Enrolment routes probed in order when `arkhe.strong_auth.route` is null.
     *
     * Only pages that actually carry 2FA or passkey controls belong here.
     * `profile.edit` and `profile.show` are deliberately absent: in the current
     * starter kit the profile page holds neither, so sending a blocked user
     * there would strand them somewhere they cannot enrol. When nothing
     * matches we render a diagnostic instead — a dead end that explains
     * itself beats a redirect that quietly goes nowhere.
     *
     * @var array<int, string>
     */
    private const ROUTE_CANDIDATES = [
        // Laravel 13 starter kit: password, 2FA and passkeys on one page.
        'security.edit',
        // Jetstream and older Fortify scaffolding.
        'two-factor.show',
    ];

    /**
     * Does this user model expose any strong-auth mechanism at all?
     *
     * Deliberately distinct from {@see satisfiedBy()}. A model that has the
     * methods and answers false to both belongs to a user who simply has not
     * enrolled yet — block them. A model carrying neither method belongs to an
     * app with no 2FA support, where *no* user could ever satisfy the
     * requirement; that is a misconfiguration, and the middleware treats it as
     * one instead of locking everyone out.
     */
    public static function isSupportedBy(?object $user): bool
    {
        return $user !== null
            && (method_exists($user, 'hasEnabledTwoFactorAuthentication')
                || method_exists($user, 'hasPasskeysEnabled'));
    }

    /**
     * Does the user hold at least one strong factor?
     *
     * TOTP is probed first on purpose: Fortify's method reads attributes off
     * an already-hydrated model and costs nothing, while the passkey probe
     * hits the database. Free check first, short-circuit on success — so the
     * query only happens for users who have no TOTP.
     *
     * Deliberately not memoized. An earlier draft cached the verdict in a
     * static keyed by class and primary key, which was wrong twice over: the
     * gate now runs once per request, so there was no second call to save; and
     * a static survives the request under Octane or Swoole, where a revoked
     * factor would keep answering `true` until the worker recycled. An
     * authorization verdict is not the kind of thing to cache across requests.
     */
    public static function satisfiedBy(?object $user): bool
    {
        if ($user === null) {
            return false;
        }

        return self::hasTwoFactor($user) || self::hasPasskey($user);
    }

    /**
     * The route name a blocked user is sent to in order to enrol, or null when
     * the host app exposes none we recognise.
     *
     * An explicit `arkhe.strong_auth.route` wins, and is honoured only if it
     * resolves — a stale name in config must not produce a broken redirect.
     * Left null, we probe the conventional names so current starter kits work
     * with no configuration at all.
     */
    public static function enrolmentRoute(): ?string
    {
        $configured = config('arkhe.strong_auth.route');

        if (is_string($configured) && $configured !== '') {
            return Route::has($configured) ? $configured : null;
        }

        foreach (self::ROUTE_CANDIDATES as $candidate) {
            if (Route::has($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The enrolment URL, or null when none can be produced.
     *
     * `Route::has()` is not enough on its own: a route that exists but takes a
     * segment — `/settings/{tenant}/security`, ordinary in a multi-tenant app —
     * passes that check and then throws when generated. Unguarded, that threw
     * from inside the interstitial, so the one page explaining the block
     * answered 500 and the blocked admin had no way back short of server
     * access. Exactly the lockout the page exists to prevent.
     *
     * Falling back to null routes the view to its diagnostic branch, which
     * names the config key to fix.
     */
    public static function enrolmentUrl(): ?string
    {
        $route = self::enrolmentRoute();

        if ($route === null) {
            return null;
        }

        try {
            return route($route);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Is the requirement being enforced?
     *
     * Deliberately all-or-nothing. An earlier draft could guard the sensitive
     * area alone, which read as prudence but was mostly self-deception: it left
     * the user list open, where accounts are created and roles handed out. Half
     * a backend protected is not half the risk, and roles and permissions
     * already draw that line properly.
     *
     * Accepts anything Laravel's env parser might hand us — `true` from a bare
     * `ARKHE_STRONG_AUTH=true`, or the strings people write meaning yes. Only
     * an explicit truthy value enables it: an unrecognised one stays off rather
     * than locking a team out of its own backend over a typo.
     */
    public static function enabled(): bool
    {
        $configured = config('arkhe.strong_auth.enforce', false);

        if (is_bool($configured)) {
            return $configured;
        }

        if (! is_string($configured)) {
            return false;
        }

        return in_array(strtolower(trim($configured)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function hasTwoFactor(object $user): bool
    {
        if (! method_exists($user, 'hasEnabledTwoFactorAuthentication')) {
            return false;
        }

        try {
            return (bool) $user->hasEnabledTwoFactorAuthentication();
        } catch (Throwable) {
            // Same reasoning as the passkey probe: a check that cannot run is
            // not allowed to be the thing that grants access.
            return false;
        }
    }

    private static function hasPasskey(object $user): bool
    {
        if (! method_exists($user, 'hasPasskeysEnabled')) {
            return false;
        }

        try {
            return (bool) $user->hasPasskeysEnabled();
        } catch (Throwable $e) {
            // The trait can sit on the model while its migration has not run —
            // typically mid-install. `exists()` then throws, and since this
            // runs in middleware every backend page would answer 500.
            // `method_exists()` does not protect against that. Treating a
            // broken probe as "no passkey" sends the user to the enrolment
            // page, which they can act on, rather than to a stack trace.
            Log::warning('[arkhe] Passkey lookup failed; treating the user as having none.', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
