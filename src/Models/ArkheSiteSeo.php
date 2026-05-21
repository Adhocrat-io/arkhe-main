<?php

declare(strict_types=1);

namespace Arkhe\Main\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton table (single row, id=1) holding the site-wide SEO defaults edited
 * via the Arkhe backend. Merged into ralphjsmit/laravel-seo's SEOData via the
 * transformer registered in the service provider.
 *
 * @property string|null $site_name
 * @property string|null $title_suffix
 * @property string|null $description
 * @property string|null $author
 * @property string|null $image
 * @property string|null $robots
 * @property string|null $twitter_username
 * @property string|null $favicon
 */
class ArkheSiteSeo extends Model
{
    protected $table = 'arkhe_site_seo';

    protected $guarded = [];
}
