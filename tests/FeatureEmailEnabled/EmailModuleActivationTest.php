<?php

declare(strict_types=1);

use Arkhe\Main\Database\Seeders\ArkheRolesSeeder;
use Arkhe\Main\Support\ArkheNav;
use Arkhe\Main\Tests\Stubs\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// The flip side of NFR-09: once an application activates the module, the
// admin surface exists — route, sidebar entry, page.
it('mounts the domain admin route when the module is enabled', function (): void {
    expect(Route::has('arkhe.email-domains.index'))->toBeTrue();
});

it('adds the sidebar entry to the settings section', function (): void {
    $keys = array_map(
        fn (object $item): string => $item->key,
        ArkheNav::section('settings')->visibleItems(null),
    );

    expect($keys)->toContain('email-domains');
});

it('serves the page to a root user', function (): void {
    $this->app->make(ArkheRolesSeeder::class)->run();

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Root',
        'last_name' => 'User',
        'email' => 'root'.uniqid().'@example.test',
        'password' => Hash::make('secret123'),
    ]);
    $user->assignRole('root');

    $this->actingAs($user)
        ->get('/administration/email-domains')
        ->assertOk()
        ->assertSee(__('arkhe::arkhe.email.domains.title'));
});
