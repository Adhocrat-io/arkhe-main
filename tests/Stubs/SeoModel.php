<?php

declare(strict_types=1);

namespace Arkhe\Main\Tests\Stubs;

use Arkhe\Main\Concerns\HasArkheSeo;
use Illuminate\Database\Eloquent\Model;

class SeoModel extends Model
{
    use HasArkheSeo;

    protected $table = 'seo_stub_models';

    protected $guarded = [];
}
