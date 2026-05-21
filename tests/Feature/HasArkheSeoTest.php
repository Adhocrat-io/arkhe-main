<?php

declare(strict_types=1);

use Arkhe\Main\Tests\Stubs\SeoModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RalphJSmit\Laravel\SEO\Models\SEO;

beforeEach(function (): void {
    if (! Schema::hasTable('seo_stub_models')) {
        Schema::create('seo_stub_models', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }
});

it('exposes a polymorphic seo relation through HasArkheSeo', function (): void {
    $model = SeoModel::query()->create(['label' => 'About']);

    expect($model->seo)->toBeInstanceOf(SEO::class);
});

it('auto-creates the SEO row on model creation', function (): void {
    $model = SeoModel::query()->create(['label' => 'Home']);

    expect(
        SEO::query()
            ->where('model_type', SeoModel::class)
            ->where('model_id', $model->getKey())
            ->exists()
    )->toBeTrue();
});

it('persists updates to the SEO row', function (): void {
    $model = SeoModel::query()->create(['label' => 'Pricing']);

    $model->seo->update([
        'title'       => 'Pricing — Acme',
        'description' => 'Our plans.',
    ]);

    expect($model->fresh()->seo->title)->toBe('Pricing — Acme');
    expect($model->fresh()->seo->description)->toBe('Our plans.');
});

it('lets seo() resolve to a model carrying HasArkheSeo', function (): void {
    $model = SeoModel::query()->create(['label' => 'Blog']);
    $model->seo->update(['title' => 'Blog — Acme']);

    $tags = (string) seo($model);

    expect($tags)->toContain('Blog — Acme');
});
