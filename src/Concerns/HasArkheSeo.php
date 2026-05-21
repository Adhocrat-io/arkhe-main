<?php

declare(strict_types=1);

namespace Arkhe\Main\Concerns;

use RalphJSmit\Laravel\SEO\Support\HasSEO;

/**
 * Opt-in trait that gives an Eloquent model a per-record SEO row backed by
 * ralphjsmit/laravel-seo. Drop it on any model (Post, Page, Product, even
 * User) to get a `seo` relation, an auto-created SEO row on `created`,
 * and `seo()->for($model)` rendering in your views.
 *
 * Today the trait is a one-line composition of HasSEO. It exists as its own
 * trait so Arkhe can add domain-specific defaults later (e.g. a
 * getDynamicSEOData() that falls back to common Arkhe accessors like
 * `full_name`, `excerpt`, …) without forcing consumers to change their
 * `use` statements.
 *
 * Usage:
 *
 *     class Post extends Model
 *     {
 *         use HasArkheSeo;
 *     }
 *
 *     // In the layout:
 *     {!! seo($post) !!}
 *
 * The site-wide defaults set via Arkhe's /administration/seo page are
 * always merged in as fallbacks — see ArkheMainServiceProvider::bootSeo().
 */
trait HasArkheSeo
{
    use HasSEO;
}
