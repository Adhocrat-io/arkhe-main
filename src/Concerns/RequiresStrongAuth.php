<?php

declare(strict_types=1);

namespace Arkhe\Main\Concerns;

use Arkhe\Main\Http\Middleware\EnsureUserHasStrongAuth;
use Arkhe\Main\Support\StrongAuth;
use Illuminate\Support\Facades\Auth;

/**
 * Re-checks the strong-factor requirement inside a backend Livewire component.
 *
 * Defence in depth for {@see EnsureUserHasStrongAuth}.
 * Route middleware guards the initial page load; Livewire's update endpoint is a
 * different route carrying only `['web']`, and while the middleware is declared
 * persistent in the service provider, Livewire decides what to re-apply from the
 * *client-supplied* snapshot path. A gate whose only enforcement can be
 * influenced by the payload it is meant to police is not a gate.
 *
 * So the requirement is asserted again here, server-side, from the session
 * user — the same shape the permission checks already take, where every
 * component calls `$this->authorize()` on mount *and* on each mutating action.
 * That per-action habit is precisely why the permission gates were never
 * exposed by this gap; strong auth needs its own equivalent.
 *
 * Aborts rather than redirects: this runs inside a component, where a redirect
 * would swap the enrolment page into the current slot. The user reaching here
 * has bypassed the front door, so the plain refusal is the right answer — the
 * explanatory page is what they get on a normal page load.
 */
trait RequiresStrongAuth
{
    /**
     * Livewire runs `booted()` on every request touching the component —
     * the initial mount and each subsequent action alike. Hooking here rather
     * than sprinkling calls through every action method means a new action
     * cannot be added without the check, which is the failure mode worth
     * designing against.
     */
    public function bootedRequiresStrongAuth(): void
    {
        $this->assertStrongAuth();
    }

    protected function assertStrongAuth(): void
    {
        if (! StrongAuth::enabled()) {
            return;
        }

        $user = Auth::user();

        if ($user === null || StrongAuth::satisfiedBy($user)) {
            return;
        }

        // No mechanism on the model at all means nobody could satisfy the
        // requirement — the middleware skips it with a logged warning rather
        // than bricking a backend nobody could re-enter, and this must agree
        // with it. Disagreeing would let a page render and then refuse every
        // action on it.
        if (! StrongAuth::isSupportedBy($user)) {
            return;
        }

        abort(403, (string) __('arkhe::arkhe.strong_auth.required'));
    }
}
