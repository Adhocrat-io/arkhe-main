<?php

declare(strict_types=1);

namespace Arkhe\Main\Services;

use Arkhe\Main\Contracts\SiteSeoRepositoryInterface;
use Arkhe\Main\Models\ArkheSiteSeo;
use Illuminate\Support\Facades\Schema;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class SiteSeoService
{
    public function __construct(
        private readonly SiteSeoRepositoryInterface $repository,
    ) {}

    public function get(): ArkheSiteSeo
    {
        return $this->repository->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): ArkheSiteSeo
    {
        return $this->repository->update($attributes);
    }

    /**
     * Merge site-wide defaults into a SEOData object. Each field is applied
     * only if the underlying value is null on the incoming SEOData and the
     * site setting is non-empty. Suffix is appended to the title when both
     * are set and the SEOData allows the suffix.
     */
    public function applyTo(SEOData $data): SEOData
    {
        if (! $this->tableReady()) {
            return $data;
        }

        $row = $this->get();

        $data->site_name ??= $row->site_name ?: null;
        $data->description ??= $row->description ?: null;
        $data->author ??= $row->author ?: null;
        $data->image ??= $row->image ?: null;
        $data->robots ??= $row->robots ?: null;
        $data->favicon ??= $row->favicon ?: null;
        $data->twitter_username ??= $row->twitter_username ?: null;

        $suffix = (string) ($row->title_suffix ?? '');
        if ($suffix !== '' && $data->enableTitleSuffix) {
            $base = (string) ($data->title ?? '');
            $data->title = $base === '' ? $suffix : trim($base.' '.$suffix);
            $data->enableTitleSuffix = false; // we already applied it
        }

        return $data;
    }

    /**
     * Guard against transformer invocation during early boot, install, or
     * tests that don't migrate the arkhe_site_seo table.
     */
    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('arkhe_site_seo');
        } catch (\Throwable) {
            return false;
        }
    }
}
