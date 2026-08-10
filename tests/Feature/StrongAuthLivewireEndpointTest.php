<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;

/**
 * Drives Livewire's REAL update endpoint rather than `Livewire::test()`.
 *
 * This distinction is the whole point of the file. The test harness skips route
 * middleware entirely, so a harness-based test passes whether or not the gate
 * is wired — it would have reported green throughout the window in which the
 * backend was, in fact, wide open to anyone replaying a snapshot.
 *
 * The hole was real and exploited end-to-end during an audit: a factorless
 * administrator, correctly redirected away from `/administration/users`, could
 * still POST an untouched snapshot to the update endpoint and delete users.
 * Route middleware guards the first GET; every action afterwards travels this
 * route, which carries only `['web']`.
 */
beforeEach(function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();

    $this->app['router']->get('/settings/security', fn () => 'security')->name('security.edit');
});

function makeEndpointUser(string $role = 'root', array $attrs = []): User
{
    /** @var User $user */
    $user = User::query()->forceCreate(array_merge([
        'first_name' => 'Endpoint',
        'last_name' => 'Tester',
        'email' => 'endpoint'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ], $attrs));

    $user->assignRole($role);

    return $user;
}

function giveConfirmedTotp(User $user): User
{
    $user->forceFill([
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

// The exploit, as a regression test. A snapshot obtained legitimately while the
// user held a factor must stop working the moment the factor is gone — the
// payload is genuine and its checksum valid, so nothing but a server-side
// re-check can refuse it.
it('refuses a replayed snapshot once the factor is revoked', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $actor = giveConfirmedTotp(makeEndpointUser('root'));
    $victim = makeEndpointUser('user');

    // Obtain a real, signed snapshot while the actor is still compliant.
    $snapshot = snapshotFromPage($this, $actor);

    // Revoke the factor. The front door now refuses this user.
    $actor->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    $this->actingAs($actor)
        ->get('/administration/users')
        ->assertRedirect(route('arkhe.strong-auth.required'));

    // Replay the untouched snapshot against the real endpoint.
    $response = $this->actingAs($actor)->withHeader('X-Livewire', '1')
        ->postJson(livewire_update_uri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => [],
                'calls' => [[
                    'path' => '',
                    'method' => 'confirmDelete',
                    'params' => [$victim->getKey()],
                ]],
            ]],
        ]);

    expect($response->getStatusCode())->toBe(403);

    // And the victim is untouched — the real assertion behind the status code.
    expect(User::query()->whereKey($victim->getKey())->exists())->toBeTrue();
});

it('still serves the endpoint to a user who holds a factor', function (): void {
    config()->set('arkhe.strong_auth.enforce', true);

    $actor = giveConfirmedTotp(makeEndpointUser('root'));

    $snapshot = snapshotFromPage($this, $actor);

    $response = $this->actingAs($actor)->withHeader('X-Livewire', '1')
        ->postJson(livewire_update_uri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => ['search' => 'anything'],
                'calls' => [],
            ]],
        ]);

    expect($response->getStatusCode())->toBe(200);
});

// With the gate off, the endpoint behaves exactly as it did before the feature
// existed — the guarantee that matters for apps upgrading without opting in.
it('leaves the endpoint alone when enforcement is disabled', function (): void {
    $actor = makeEndpointUser('root');

    $snapshot = snapshotFromPage($this, $actor);

    $response = $this->actingAs($actor)->withHeader('X-Livewire', '1')
        ->postJson(livewire_update_uri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => ['search' => 'anything'],
                'calls' => [],
            ]],
        ]);

    expect($response->getStatusCode())->toBe(200);
});

/**
 * Livewire prefixes its endpoint with a per-boot random segment, so the URI has
 * to be resolved from the routing table at call time rather than remembered.
 * Under Testbench the route is named `default-livewire.update`; in a host app
 * it is `livewire.update`. Match on the suffix so this holds either way.
 */
function livewire_update_uri(): string
{
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        if (str_ends_with((string) $route->getName(), 'livewire.update')) {
            return '/'.ltrim($route->uri(), '/');
        }
    }

    throw new RuntimeException('Livewire update route not registered.');
}

/**
 * Pull a live snapshot out of the rendered page, exactly as a browser — or an
 * attacker with a browser — would. Deliberately not `Livewire::test()`'s
 * internals: the point of this file is to exercise the real path, and a
 * snapshot lifted from real HTML is the same artefact the exploit replays.
 *
 * @return array<string, mixed>
 */
function snapshotFromPage(object $test, User $actor): array
{
    $html = $test->actingAs($actor)->get('/administration/users')->getContent();

    $encoded = (string) str($html)->betweenFirst('wire:snapshot="', '"');

    return json_decode(html_entity_decode($encoded), true, 512, JSON_THROW_ON_ERROR);
}
