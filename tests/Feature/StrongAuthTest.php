<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Livewire\StrongAuthRequired;
use Arkhe\Main\Tests\Stubs\User;
use Arkhe\Main\Tests\Stubs\UserWithoutStrongAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();

    // Testbench ships no starter kit, so no enrolment route exists and every
    // refusal would render the diagnostic notice instead of redirecting.
    // Registering a stand-in lets the redirect cases assert what they mean to.
    $this->app['router']->get('/settings/security', fn () => 'security')->name('security.edit');
});

function makeStrongAuthUser(string $role = 'administrateur', array $attrs = []): User
{
    /** @var User $user */
    $user = User::query()->forceCreate(array_merge([
        'first_name' => 'Strong',
        'last_name' => 'Tester',
        'email' => 'strong'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ], $attrs));

    $user->assignRole($role);

    return $user;
}

/** Give a user a confirmed TOTP, the way Fortify's confirmation step would. */
function withConfirmedTotp(User $user): User
{
    $user->forceFill([
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

function withPasskey(User $user): User
{
    DB::table('passkeys')->insert([
        'user_id' => $user->getKey(),
        'name' => 'MacBook',
        'credential_id' => 'cred-'.uniqid(),
        'credential' => json_encode(['fake' => true]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

// ─── Middleware wiring ───────────────────────────────────────────────────

// Guards a regression that briefly shipped during development: chaining a
// second ->middleware() call on the route group REPLACED the stack instead of
// extending it, silently dropping `auth` and `arkhe.backend` and leaving the
// whole backend open. The gate being present matters far less than the gates
// before it surviving.
it('appends the gate to the configured stack without displacing it', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes());

    $users = $routes->first(fn ($r) => $r->uri() === 'administration/users')->gatherMiddleware();
    expect($users)->toBe(['web', 'auth', 'arkhe.backend', 'arkhe.strong-auth']);

    // The sensitive area inherits the gate from the outer group and adds only
    // its permission check — the requirement is all-or-nothing, so a second
    // pass would buy nothing. The gate therefore runs first here, which also
    // means a factorless user learns nothing about the sensitive area until
    // they have enrolled.
    $roles = $routes->first(fn ($r) => $r->uri() === 'administration/roles')->gatherMiddleware();
    expect($roles)->toBe([
        'web', 'auth', 'arkhe.backend', 'arkhe.strong-auth', 'arkhe.root',
    ]);
});

// ─── Disabled by default ─────────────────────────────────────────────────

it('lets a user with no strong factor reach the backend by default', function (): void {
    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/users')
        ->assertOk();
});

// The default has to hold for the sensitive area too, since that group carries
// an extra middleware and could plausibly diverge.
it('leaves the sensitive area open by default too', function (): void {
    $this->actingAs(makeStrongAuthUser('root'))
        ->get('/administration/roles')
        ->assertOk();
});

// A typo in .env must never be what locks a team out of its own backend, so
// anything unrecognised reads as disabled rather than as "protect everything".
it('treats an unrecognised enforce value as disabled', function (mixed $value): void {
    config()->set('arkhe.strong_auth.enforce', $value);

    $this->actingAs(makeStrongAuthUser('root'))
        ->get('/administration/roles')
        ->assertOk();
})->with(['backend', 'root', 'maybe', 'off', '', 0]);

// ─── Enforcing ───────────────────────────────────────────────────────────

it('covers the sensitive area from the outer group', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(makeStrongAuthUser('root'))
        ->get('/administration/roles')
        ->assertRedirect(route('arkhe.strong-auth.required'));
});

it('admits a root carrying a confirmed TOTP', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(withConfirmedTotp(makeStrongAuthUser('root')))
        ->get('/administration/roles')
        ->assertOk();
});

it('admits a root carrying a passkey', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(withPasskey(makeStrongAuthUser('root')))
        ->get('/administration/roles')
        ->assertOk();
});

// The strong-auth gate now sits on the outer group, so it answers before the
// permission check on nested routes. A user lacking `manage-roles` is sent to
// enrol rather than told 403 — which is the better order anyway: it says
// nothing about whether the sensitive area exists or whether they could enter
// it. They meet the 403 after enrolling, once they are entitled to an answer.
it('asks for the factor before revealing the permission verdict', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/roles')
        ->assertRedirect(route('arkhe.strong-auth.required'));
});

// ...and once the factor is there, the permission check answers normally.
it('still refuses the sensitive area to a user without the permission', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(withPasskey(makeStrongAuthUser('administrateur')))
        ->get('/administration/roles')
        ->assertForbidden();
});

it('redirects a factorless user away from the whole backend', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/users')
        ->assertRedirect(route('arkhe.strong-auth.required'));
});

it('admits a user with a confirmed TOTP', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(withConfirmedTotp(makeStrongAuthUser('administrateur')))
        ->get('/administration/users')
        ->assertOk();
});

// The headline requirement, stated as a test: a passkey is already two-factor
// and phishing-resistant, so it exempts from TOTP entirely.
it('admits a user with a passkey and no TOTP at all', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $user = withPasskey(makeStrongAuthUser('administrateur'));
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->actingAs($user)
        ->get('/administration/users')
        ->assertOk();
});

// Half-enrolled is a real, reachable state: Fortify writes the secret before
// the user confirms it. It must not count.
it('rejects a TOTP secret that was never confirmed', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $user = makeStrongAuthUser('administrateur');
    $user->forceFill(['two_factor_secret' => 'encrypted-secret'])->save();

    $this->actingAs($user)
        ->get('/administration/users')
        ->assertRedirect(route('arkhe.strong-auth.required'));
});

// A JSON caller cannot follow a redirect meaningfully — a Livewire request
// would swap the interstitial into the current component's slot — so it gets
// the status and the message instead.
it('answers 403 rather than redirecting for JSON callers', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(makeStrongAuthUser('root'))
        ->getJson('/administration/users')
        ->assertStatus(403);
});

it('still refuses anonymous visitors to the login page, not the gate', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->get('/administration/users')->assertRedirect(route('login'));
});

// ─── Degraded states ─────────────────────────────────────────────────────

// Enforcement asked for on an app whose user model has no mechanism at all:
// nobody could satisfy it, and failing closed would brick the backend with no
// way back in, since the config is only reachable with server access.
it('falls open with a warning when the user model supports neither factor', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);
    config()->set('auth.providers.users.model', UserWithoutStrongAuth::class);

    Log::shouldReceive('warning')->once()->withArgs(
        fn (string $message): bool => str_contains($message, 'hasEnabledTwoFactorAuthentication'),
    );

    /** @var UserWithoutStrongAuth $user */
    $user = UserWithoutStrongAuth::query()->forceCreate([
        'first_name' => 'No',
        'last_name' => 'Factor',
        'email' => 'nofactor@example.test',
        'password' => Hash::make('secret123'),
    ]);
    $user->assignRole('administrateur');

    $this->actingAs($user)
        ->get('/administration/users')
        ->assertOk();
});

// ─── The interstitial ────────────────────────────────────────────────────

// The page that explains the block must never be behind the block. Guarded, it
// would refuse the only screen telling the user how to get through, and bounce
// them between the two forever.
it('leaves the explanation page reachable while enforcement is on', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/strong-auth')
        ->assertOk()
        ->assertSee(__('arkhe::arkhe.strong_auth.page.title'));
});

// Asserts the component's own output, not the rendered anchor: Flux is stubbed
// out in this suite (see TestCase), so `flux:button` emits no href to look for.
// The URL the page hands off to is the component's answer, and that is what
// matters here.
it('offers a way through to the enrolment page', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/strong-auth')
        ->assertOk()
        ->assertSee(__('arkhe::arkhe.strong_auth.page.cta'))
        ->assertSee(__('arkhe::arkhe.strong_auth.page.steps.find'));

    expect(app(StrongAuthRequired::class)->enrolmentUrl())
        ->toBe(route('security.edit'));
});

// Nowhere to hand off to: say so rather than link into the void. This is
// addressed to whoever set the flag, not to the user who ran into it.
it('reports the misconfiguration when no enrolment route resolves', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);
    config()->set('arkhe.strong_auth.route', 'nonexistent.route');

    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/strong-auth')
        ->assertOk()
        ->assertSee(__('arkhe::arkhe.strong_auth.page.no_route_title'))
        ->assertDontSee(__('arkhe::arkhe.strong_auth.page.cta'));
});

// Still reachable when the gate is off: a stale link or a back button should
// find a page, not a 404.
it('stays registered when enforcement is disabled', function (): void {
    $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/strong-auth')
        ->assertOk();
});

// An anonymous visitor has nothing to do here — `auth` still applies.
it('refuses anonymous visitors to the explanation page', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $this->get('/administration/strong-auth')->assertRedirect(route('login'));
});

// Follow the chain the way a browser would. A gate that redirects to a page it
// also guards is the classic way to build an infinite loop, so walk it to a
// settled page rather than trusting the wiring by eye.
it('settles on the explanation instead of looping', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $response = $this->actingAs(makeStrongAuthUser('administrateur'))
        ->get('/administration/users');

    $hops = 0;
    while ($response->isRedirect() && $hops < 5) {
        $response = $this->get($response->headers->get('Location'));
        $hops++;
    }

    expect($hops)->toBe(1)
        ->and($response->getStatusCode())->toBe(200);

    $response->assertSee(__('arkhe::arkhe.strong_auth.page.title'));
});
