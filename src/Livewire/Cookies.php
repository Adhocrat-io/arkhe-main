<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Concerns\RequiresStrongAuth;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Whitecube\LaravelCookieConsent\CookiesRegistrar;

/**
 * Read-only admin viewer of the cookies currently registered through
 * whitecube/laravel-cookie-consent. Lists every category + cookie so site
 * owners can audit what gets tracked. Adding/removing cookies happens in
 * code (a CookiesServiceProvider subclass) — Arkhe ships sensible defaults
 * (Laravel session + CSRF as essentials); consumers extend via their own
 * provider.
 */
class Cookies extends Component
{
    use RequiresStrongAuth;

    public function mount(): void
    {
        $this->authorize('view-cookies');
    }

    /**
     * Renders a duration in minutes readably: "525600 min" says nothing to
     * someone auditing what the site drops, "1 year" is understood at once.
     * Zero means "for the session", `null` an undeclared duration.
     */
    private function humanDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes === 0) {
            return __('arkhe::arkhe.cookies.session');
        }

        // The steps are approximate on purpose: we give the order of
        // magnitude, we do not count leap days.
        foreach ([
            ['unit' => 'years', 'minutes' => 525600],
            ['unit' => 'months', 'minutes' => 43200],
            ['unit' => 'days', 'minutes' => 1440],
            ['unit' => 'hours', 'minutes' => 60],
        ] as $step) {
            if ($minutes >= $step['minutes']) {
                $value = (int) round($minutes / $step['minutes']);

                return trans_choice('arkhe::arkhe.cookies.duration.'.$step['unit'], $value, ['count' => $value]);
            }
        }

        return trans_choice('arkhe::arkhe.cookies.duration.minutes', $minutes, ['count' => $minutes]);
    }

    /**
     * The registrar's descriptions are translation keys
     * (`cookieConsent::cookies.defaults.session`). They only resolve if the
     * app published the package's languages — otherwise `__()` returns the
     * key itself, which showed up on screen as-is. We would rather say
     * nothing than display a key.
     */
    private function resolveDescription(mixed $description): string
    {
        $description = (string) ($description ?? '');

        if ($description === '') {
            return '';
        }

        $translated = (string) __($description);

        return $translated === $description && str_contains($description, '::')
            ? ''
            : $translated;
    }

    public function render(CookiesRegistrar $registrar): View
    {
        $categories = collect($registrar->getCategories())->map(function ($category): array {
            return [
                'key'         => $category->key(),
                'title'       => (string) $category->getAttribute('title'),
                'description' => (string) ($category->getAttribute('description') ?? ''),
                'cookies'     => collect($category->getCookies())->map(function ($cookie): array {
                    return [
                        'name'        => (string) ($cookie->name ?? '—'),
                        'duration'    => $this->humanDuration(isset($cookie->duration) ? (int) $cookie->duration : null),
                        'description' => $this->resolveDescription($cookie->getAttribute('description')),
                    ];
                })->all(),
            ];
        })->all();

        return view('arkhe::livewire.cookies', [
            'categories' => $categories,
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
