<?php

declare(strict_types=1);

namespace Arkhe\Main\Contracts;

use Arkhe\Main\Models\ArkheSiteSeo;

interface SiteSeoRepositoryInterface
{
    /**
     * Get the singleton site SEO row, creating it on first access.
     */
    public function get(): ArkheSiteSeo;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): ArkheSiteSeo;
}
