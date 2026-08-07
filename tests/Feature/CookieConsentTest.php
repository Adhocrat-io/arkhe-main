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
        // Un cookie réellement listé : c'est ce que l'écran doit montrer à qui
        // vient auditer ce que le site dépose. Le cookie de session porte le
        // nom configuré par l'app, qui varie ; `XSRF-TOKEN`, lui, est fixe.
        ->assertSee('XSRF-TOKEN');
});

// Les durées du registre sont en minutes : « 525600 min » ne dit rien à qui
// vient auditer, on les rend lisibles. L'assertion porte sur la valeur brute
// qui doit disparaître, pas sur le libellé — celui-ci dépend de la langue.
it('renders cookie durations in plain language', function (): void {
    $root = makeCookieUser('root');

    Livewire::actingAs($root)
        ->test(Cookies::class)
        ->assertSee(trans_choice('arkhe::arkhe.cookies.duration.years', 1, ['count' => 1]))
        ->assertDontSee('525600');
});

// Les descriptions du registre sont des clés de traduction que le paquet ne
// publie pas : elles s'affichaient brutes à l'écran.
it('never renders an unresolved translation key', function (): void {
    $root = makeCookieUser('root');

    Livewire::actingAs($root)
        ->test(Cookies::class)
        ->assertDontSee('cookieConsent::');
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
