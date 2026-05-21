<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\Cookies;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Whitecube\LaravelCookieConsent\CookiesRegistrar;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();
    config()->set('arkhe.admin.layout', '');
});

function makeCookieUser(?string $role = null): User
{
    /** @var User $u */
    $u = User::query()->forceCreate([
        'first_name' => 'Cookie',
        'last_name'  => 'User',
        'email'      => 'cookie-'.uniqid().'@x.test',
        'password'   => Hash::make('x'),
    ]);

    if ($role !== null) {
        $u->assignRole($role);
    }

    return $u;
}

it('pre-registers Laravel essentials (session + CSRF) automatically', function (): void {
    $registrar = app(CookiesRegistrar::class);
    $essentials = $registrar->essentials();
    $names = collect($essentials->getCookies())
        ->map(fn ($c) => isset($c->name) ? (string) $c->name : null)
        ->filter()
        ->values()
        ->all();

    expect($names)->toContain('XSRF-TOKEN');
    // Session cookie name comes from config('session.cookie'); just assert
    // we registered more than the consent cookie.
    expect(count($names))->toBeGreaterThanOrEqual(2);
});

it('renders the cookies admin viewer for a root user', function (): void {
    $root = makeCookieUser('root');

    Livewire::actingAs($root)
        ->test(Cookies::class)
        ->assertStatus(200)
        ->assertSee('essentials');
});

it('blocks a non-root visitor from /administration/cookies via the arkhe.root middleware', function (): void {
    $admin = makeCookieUser('administrateur');

    $this->actingAs($admin)
        ->get('/administration/cookies')
        ->assertForbidden();
});

it('blocks a regular user from the cookies Livewire component', function (): void {
    $u = makeCookieUser('user');

    Livewire::actingAs($u)
        ->test(Cookies::class)
        ->assertForbidden();
});
