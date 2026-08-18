<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Support\StrongAuth;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The page the strong-auth gate sends blocked users to.
 *
 * It exists because the enrolment screen belongs to the host app and usually
 * sits behind `password.confirm`: redirecting straight there consumed the
 * flashed message, so the user met a password prompt and a settings page with
 * nothing saying what was wanted of them. An interstitial owned by the package
 * states the requirement where it is guaranteed to be read, then hands off — so
 * the password prompt arrives after the explanation rather than instead of it.
 *
 * A Livewire component rather than a plain view so it renders through the same
 * `->layout()` path as every other Arkhe page: the host layout may take a slot
 * or a section, and Livewire already knows how to tell the two apart.
 *
 * Its route deliberately sits outside the guarded group — see routes/arkhe.php.
 */
class StrongAuthRequired extends Component
{
    /**
     * Where the user goes to enrol, or null when the host app exposes no page
     * we recognise. The view drops its call to action in that case and reports
     * the misconfiguration instead of linking nowhere.
     */
    #[Computed]
    public function enrolmentUrl(): ?string
    {
        return StrongAuth::enrolmentUrl();
    }

    public function render(): View
    {
        return view('arkhe::livewire.strong-auth-required')
            ->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
