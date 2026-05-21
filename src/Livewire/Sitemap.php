<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\SiteSeoRepositoryInterface;
use Arkhe\Main\Jobs\GenerateSitemap;
use Arkhe\Main\Services\SitemapService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Sitemap extends Component
{
    public function mount(): void
    {
        $this->authorize('view-sitemap');
    }

    /**
     * Dispatch the GenerateSitemap job onto the host app's queue. If the
     * `sync` driver is in use, the job runs immediately — same effect as
     * calling the service directly, just with a uniform code path.
     */
    public function regenerate(): void
    {
        $this->authorize('update-sitemap');

        GenerateSitemap::dispatch();

        session()->flash('arkhe.sitemap.dispatched', true);
    }

    public function render(
        SitemapService $service,
        SiteSeoRepositoryInterface $repository,
    ): View {
        $row = $repository->get();

        return view('arkhe::livewire.sitemap', [
            'enabled'      => $service->isEnabled(),
            'url'          => $service->url(),
            'outputPath'   => $service->outputPath(),
            'schedule'     => (string) config('arkhe.sitemap.schedule', '0 3 * * *'),
            'lastGenerated' => $row->sitemap_generated_at,
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
