<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

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
    public function mount(): void
    {
        $this->authorize('view-cookies');
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
                        'duration'    => isset($cookie->duration) ? (int) $cookie->duration : null,
                        'description' => (string) ($cookie->getAttribute('description') ?? ''),
                    ];
                })->all(),
            ];
        })->all();

        return view('arkhe::livewire.cookies', [
            'categories' => $categories,
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
