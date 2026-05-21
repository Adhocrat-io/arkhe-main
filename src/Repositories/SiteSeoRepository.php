<?php

declare(strict_types=1);

namespace Arkhe\Main\Repositories;

use Arkhe\Main\Contracts\SiteSeoRepositoryInterface;
use Arkhe\Main\Models\ArkheSiteSeo;

class SiteSeoRepository implements SiteSeoRepositoryInterface
{
    public function get(): ArkheSiteSeo
    {
        $row = ArkheSiteSeo::query()->first();

        if ($row === null) {
            $row = new ArkheSiteSeo;
            $row->save();
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): ArkheSiteSeo
    {
        $row = $this->get();
        $row->fill($attributes)->save();

        return $row->refresh();
    }
}
