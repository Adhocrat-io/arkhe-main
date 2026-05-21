<?php

declare(strict_types=1);

namespace Arkhe\Main\Jobs;

use Arkhe\Main\Services\SitemapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate the host app's sitemap on a queue. Scheduled daily by the package
 * (configurable via `arkhe.sitemap.schedule`) and dispatchable on demand from
 * the /administration/sitemap admin page.
 */
class GenerateSitemap implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SitemapService $service): void
    {
        $service->generate();
    }
}
