<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\SiteSeoRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Spatie\Sitemap\SitemapGenerator;

class SitemapService
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly SiteSeoRepositoryInterface $repository,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('arkhe.sitemap.enabled', true);
    }

    public function url(): string
    {
        $configured = (string) $this->config->get('arkhe.sitemap.url', '');

        return $configured !== '' ? $configured : (string) $this->config->get('app.url');
    }

    public function outputPath(): string
    {
        $configured = (string) $this->config->get('arkhe.sitemap.path', '');

        return $configured !== '' ? $configured : public_path('sitemap.xml');
    }

    /**
     * Generate the sitemap and stamp the run on the site_seo row. Returns the
     * timestamp at which the run completed, or null when the integration is
     * disabled.
     */
    public function generate(): ?CarbonImmutable
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $generator = SitemapGenerator::create($this->url());

        $this->configureGenerator($generator);

        $generator->writeToFile($this->outputPath());

        $now = CarbonImmutable::now();

        $this->repository->update(['sitemap_generated_at' => $now]);

        return $now;
    }

    /**
     * Hook for host apps / subclasses: customize the generator (extra URLs,
     * crawl profile, filters) before it writes. Default is a no-op.
     */
    protected function configureGenerator(SitemapGenerator $generator): void
    {
    }
}
