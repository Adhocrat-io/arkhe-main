<?php

declare(strict_types=1);

namespace Arkhe\Main\Http\Middleware;

use Arkhe\Main\Support\StrongAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires one strong factor — a registered passkey OR a confirmed TOTP —
 * before a user reaches the backend.
 *
 * This gate answers "are you protected enough to be here?", which sits beside
 * the questions the other two Arkhe middlewares already ask ("do you have the
 * permission?"). It is authorization, not authentication: how a user signs in
 * belongs to the host app's Fortify pipeline, which the package does not touch.
 * A blocked user stays perfectly signed in — only `/administration/*` closes
 * until they enrol.
 *
 * Fortify ships no equivalent. It offers two-factor authentication but never
 * compels it: `RedirectIfTwoFactorAuthenticatable` presents the challenge to
 * users who already enabled it, and there is no route middleware anywhere in
 * the package. Requiring enrolment is app policy, so it lands here.
 *
 * All or nothing: `arkhe.strong_auth.enforce` either protects the backend or it
 * does not. An earlier draft could guard the sensitive area alone, which read as
 * prudence but left the user list open — where accounts are created and roles
 * handed out. Roles and permissions already draw that line, and drawing a
 * second, weaker one here only bought a false sense of safety.
 *
 * Off by default, so a version bump never locks an app out of its own backend.
 *
 * Wired directly in routes/arkhe.php rather than through
 * `config('arkhe.middleware')`: apps that published their config before this
 * release carry a frozen middleware array on disk, and a new package default
 * would never reach them. Same shape as the sitemap scheduler — always wired,
 * inert unless its flag says otherwise.
 */
class EnsureUserHasStrongAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StrongAuth::enabled()) {
            return $next($request);
        }

        $user = $request->user();

        // `auth` runs ahead of us in every group the package ships. Reaching
        // this without a user means someone mounted the alias without it; let
        // the request through rather than guess, and leave the 403 to the gate
        // that actually knows.
        if ($user === null) {
            return $next($request);
        }

        if (StrongAuth::satisfiedBy($user)) {
            return $next($request);
        }

        if (! StrongAuth::isSupportedBy($user)) {
            return $this->skipUnsatisfiable($request, $next, $user);
        }

        return $this->deny($request);
    }

    /**
     * Enforcement was asked for, but the user model exposes no mechanism at
     * all — no user could ever satisfy it.
     *
     * Failing closed would brick the backend with no way back in: the config
     * that caused it is only reachable with server access, and the enrolment
     * page cannot help someone whose app has nothing to enrol. Failing open
     * keeps the backend exactly as it was and says so loudly, which is the
     * proportionate answer to what is almost certainly a misconfiguration —
     * nobody deliberately requires 2FA on an app that has none.
     *
     * The asymmetry is what makes this safe: this path needs *both* methods to
     * be absent. A model that has them and returns false is blocked normally,
     * so an app with real 2FA support can never fall through here.
     */
    private function skipUnsatisfiable(Request $request, Closure $next, object $user): Response
    {
        Log::warning(
            '[arkhe] `arkhe.strong_auth.enforce` is set but the user model exposes neither '
            .'hasEnabledTwoFactorAuthentication() nor hasPasskeysEnabled(). Nobody could satisfy '
            .'the requirement, so it is being skipped. Install laravel/fortify (TOTP) or '
            .'laravel/passkeys, or set the flag back to false.',
            ['user_model' => $user::class],
        );

        return $next($request);
    }

    /**
     * Send the user to the page that explains the block.
     *
     * Not straight to the enrolment page, which is where this first landed and
     * where it was wrong: the host app's security page usually sits behind
     * `password.confirm`, and that intermediate hop consumes any flashed
     * message. The user arrived at a settings screen having been asked for
     * their password for no stated reason, with nothing telling them a passkey
     * or a TOTP was expected.
     *
     * An interstitial owned by the package fixes that for good. It depends on
     * nothing in the host app, states the requirement in the user's language,
     * and only then hands off — so the password prompt arrives *after* the
     * explanation rather than instead of it.
     *
     * A bare 403 was never an option: the block is real but thirty seconds
     * from being resolved, and an admin who enables `backend` before enrolling
     * would otherwise have no way back in short of server access.
     */
    private function deny(Request $request): Response
    {
        // Livewire and API callers cannot follow a redirect meaningfully — a
        // Livewire request would swap the target page into the current
        // component's slot. They get the status and the message instead. Same
        // shape as Laravel's own `Authenticate` middleware.
        if ($request->expectsJson()) {
            abort(403, (string) __('arkhe::arkhe.strong_auth.required'));
        }

        return redirect()->route('arkhe.strong-auth.required');
    }
}
