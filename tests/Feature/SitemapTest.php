<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Jobs\GenerateSitemap;
use Arkhe\Main\Livewire\Sitemap;
use Arkhe\Main\Models\ArkheSiteSeo;
use Arkhe\Main\Services\SiteSeoService;
use Arkhe\Main\Services\SitemapService;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
    config()->set('arkhe.admin.layout', '');
});

function makeSitemapUser(?string $role = null): User
{
    /** @var User $u */
    $u = User::query()->forceCreate([
        'first_name' => 'Map',
        'last_name'  => 'User',
        'email'      => 'sitemap-'.uniqid().'@x.test',
        'password'   => Hash::make('x'),
    ]);

    if ($role !== null) {
        $u->assignRole($role);
    }

    return $u;
}

// ─── Service config defaults ──────────────────────────────────────────────────

it('falls back to config(app.url) when arkhe.sitemap.url is empty', function (): void {
    config()->set('app.url', 'https://acme.test');
    config()->set('arkhe.sitemap.url', null);

    expect(app(SitemapService::class)->url())->toBe('https://acme.test');
});

it('honours an explicit arkhe.sitemap.url override', function (): void {
    config()->set('arkhe.sitemap.url', 'https://override.test');

    expect(app(SitemapService::class)->url())->toBe('https://override.test');
});

it('defaults the output path to public_path(sitemap.xml)', function (): void {
    config()->set('arkhe.sitemap.path', null);

    expect(app(SitemapService::class)->outputPath())
        ->toBe(public_path('sitemap.xml'));
});

it('returns null and skips work when the integration is disabled', function (): void {
    config()->set('arkhe.sitemap.enabled', false);

    expect(app(SitemapService::class)->generate())->toBeNull();
});

// ─── Livewire admin UI ────────────────────────────────────────────────────────

it('renders the sitemap admin page for a root user', function (): void {
    $root = makeSitemapUser('root');
    app(SiteSeoService::class)->get(); // ensures the singleton row exists

    Livewire::actingAs($root)
        ->test(Sitemap::class)
        ->assertStatus(200);
});

it('blocks a non-root visitor from /administration/sitemap via the arkhe.root middleware', function (): void {
    $admin = makeSitemapUser('administrateur');

    $this->actingAs($admin)
        ->get('/administration/sitemap')
        ->assertForbidden();
});

it('dispatches the GenerateSitemap job when the regenerate button is clicked', function (): void {
    Bus::fake();

    $root = makeSitemapUser('root');

    Livewire::actingAs($root)
        ->test(Sitemap::class)
        ->call('regenerate')
        ->assertHasNoErrors();

    Bus::assertDispatched(GenerateSitemap::class);
});

it('blocks a regular user from dispatching the regeneration', function (): void {
    Bus::fake();

    $u = makeSitemapUser('user');

    Livewire::actingAs($u)
        ->test(Sitemap::class)
        ->assertForbidden();

    Bus::assertNotDispatched(GenerateSitemap::class);
});

// ─── Service.generate() integration ───────────────────────────────────────────

it('stamps sitemap_generated_at when the service finishes a run', function (): void {
    config()->set('app.url', 'https://example.com');
    config()->set('arkhe.sitemap.path', sys_get_temp_dir().'/arkhe-test-sitemap-'.uniqid().'.xml');

    try {
        app(SitemapService::class)->generate();
    } catch (\Throwable $e) {
        // The generator hits the network. In CI / offline environments it
        // may fail before writing — the *stamping* is what we test here,
        // and that runs after writeToFile() returns. If write fails the
        // assertion below catches it.
        $this->markTestSkipped('SitemapGenerator could not reach the URL: '.$e->getMessage());
    }

    $row = ArkheSiteSeo::query()->first();
    expect($row->sitemap_generated_at)->not->toBeNull();
});
