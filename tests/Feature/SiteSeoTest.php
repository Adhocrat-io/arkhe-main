<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\SiteSeo;
use Arkhe\Main\Models\ArkheSiteSeo;
use Arkhe\Main\Services\SiteSeoService;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use RalphJSmit\Laravel\SEO\Support\SEOData;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
    config()->set('arkhe.admin.layout', '');
});

function makeSeoUser(?string $role = null): User
{
    /** @var User $u */
    $u = User::query()->forceCreate([
        'first_name' => 'Seo',
        'last_name'  => 'User',
        'email'      => 'seo-'.uniqid().'@x.test',
        'password'   => Hash::make('x'),
    ]);

    if ($role !== null) {
        $u->assignRole($role);
    }

    return $u;
}

// ─── Repository / Service ─────────────────────────────────────────────────────

it('lazily creates the singleton row on first access', function (): void {
    expect(ArkheSiteSeo::query()->count())->toBe(0);

    $row = app(SiteSeoService::class)->get();

    expect($row)->toBeInstanceOf(ArkheSiteSeo::class);
    expect(ArkheSiteSeo::query()->count())->toBe(1);
});

it('updates the singleton row without creating duplicates', function (): void {
    $service = app(SiteSeoService::class);

    $service->update(['site_name' => 'Acme']);
    $service->update(['site_name' => 'Acme Corp']);

    expect(ArkheSiteSeo::query()->count())->toBe(1);
    expect($service->get()->site_name)->toBe('Acme Corp');
});

// ─── applyTo() merge logic ────────────────────────────────────────────────────

it('fills empty SEOData fields with site defaults', function (): void {
    app(SiteSeoService::class)->update([
        'site_name'        => 'Acme',
        'description'      => 'Default description',
        'author'           => 'Acme Team',
        'image'            => '/og.png',
        'twitter_username' => 'acme',
    ]);

    $data = new SEOData;
    $merged = app(SiteSeoService::class)->applyTo($data);

    expect($merged->site_name)->toBe('Acme');
    expect($merged->description)->toBe('Default description');
    expect($merged->author)->toBe('Acme Team');
    expect($merged->image)->toBe('/og.png');
    expect($merged->twitter_username)->toBe('acme');
});

it('does not overwrite SEOData fields that already carry a value', function (): void {
    app(SiteSeoService::class)->update([
        'description' => 'Site default',
        'image'       => '/site-default.png',
    ]);

    $data = new SEOData(
        description: 'Page-specific description',
        image: '/page-specific.png',
    );

    $merged = app(SiteSeoService::class)->applyTo($data);

    expect($merged->description)->toBe('Page-specific description');
    expect($merged->image)->toBe('/page-specific.png');
});

it('appends the title suffix when one is configured', function (): void {
    app(SiteSeoService::class)->update(['title_suffix' => '| Acme']);

    $data = new SEOData(title: 'About');
    $merged = app(SiteSeoService::class)->applyTo($data);

    expect($merged->title)->toBe('About | Acme');
    expect($merged->enableTitleSuffix)->toBeFalse();
});

it('uses the title suffix as the title when SEOData has no title', function (): void {
    app(SiteSeoService::class)->update(['title_suffix' => 'Acme — Backend']);

    $data = new SEOData;
    $merged = app(SiteSeoService::class)->applyTo($data);

    expect($merged->title)->toBe('Acme — Backend');
});

// ─── Livewire admin UI ────────────────────────────────────────────────────────

it('loads the singleton row into the form on mount', function (): void {
    $root = makeSeoUser('root');
    app(SiteSeoService::class)->update([
        'site_name'    => 'Acme',
        'title_suffix' => '| Acme',
    ]);

    Livewire::actingAs($root)
        ->test('arkhe.site-seo')
        ->assertSet('siteSeoForm.site_name', 'Acme')
        ->assertSet('siteSeoForm.title_suffix', '| Acme');
});

it('saves the form back to the singleton row', function (): void {
    $root = makeSeoUser('root');

    Livewire::actingAs($root)
        ->test('arkhe.site-seo')
        ->set('siteSeoForm.site_name', 'New Acme')
        ->set('siteSeoForm.description', 'Hello world')
        ->call('save')
        ->assertHasNoErrors();

    $row = ArkheSiteSeo::query()->first();
    expect($row->site_name)->toBe('New Acme');
    expect($row->description)->toBe('Hello world');
});

it('blocks a non-root user from the /administration/seo route', function (): void {
    $admin = makeSeoUser('administrateur');

    $this->actingAs($admin)
        ->get('/administration/seo')
        ->assertForbidden();
});

it('blocks a guest visitor from the SiteSeo Livewire component', function (): void {
    $u = makeSeoUser('user'); // no manage-roles permission

    Livewire::actingAs($u)
        ->test('arkhe.site-seo')
        ->assertForbidden();
});
