<?php

declare(strict_types=1);

use Arkhe\Main\Support\ArkheNav;
use Arkhe\Main\Tests\Stubs\User;
use Spatie\Permission\Models\Permission;

afterEach(function (): void {
    // Custom sections registered in a test must not leak into the next one.
    // The next app boot re-seeds Arkhè's own defaults.
    ArkheNav::flush();
});

it('seeds the default Accès and Réglages sections in order', function (): void {
    $keys = collect(ArkheNav::all())->pluck('key');

    expect($keys)->toContain('access', 'settings')
        ->and($keys->search('access'))->toBeLessThan($keys->search('settings'));
});

it('lets a package add an item to an existing section', function (): void {
    ArkheNav::section('settings')->item(
        key: 'billing',
        label: 'Billing',
        icon: 'credit-card',
        route: 'arkhe.users.index',
        priority: 99,
    );

    $settings = collect(ArkheNav::all())->firstWhere('key', 'settings');
    $keys = collect($settings->visibleItems(null))->pluck('key');

    expect($keys)->toContain('billing');
});

it('orders items within a section by priority', function (): void {
    ArkheNav::section('demo', heading: 'Demo')
        ->item('b', 'B', 'star', route: 'arkhe.users.index', priority: 20)
        ->item('a', 'A', 'star', route: 'arkhe.users.index', priority: 10);

    $items = collect(ArkheNav::section('demo')->visibleItems(null))->pluck('key');

    expect($items->all())->toBe(['a', 'b']);
});

it('hides a section when its gate fails and shows it when it passes', function (): void {
    ArkheNav::section('secret', heading: 'Secret', can: static fn (?object $u): bool => $u !== null)
        ->item('x', 'X', 'star', route: 'arkhe.users.index');

    expect(collect(ArkheNav::sectionsFor(null))->pluck('key'))->not->toContain('secret')
        ->and(collect(ArkheNav::sectionsFor(new User))->pluck('key'))->toContain('secret');
});

it('hides a section that has no visible items', function (): void {
    ArkheNav::section('empty', heading: 'Empty')
        ->item('gated', 'Gated', 'star', route: 'arkhe.users.index', can: static fn (): bool => false);

    expect(collect(ArkheNav::sectionsFor(null))->pluck('key'))->not->toContain('empty');
});

it('gates an item by a permission string against the user', function (): void {
    Permission::findOrCreate('view-demo');

    $allowed = User::create(['email' => 'a@demo.test', 'password' => bcrypt('secret')]);
    $allowed->givePermissionTo('view-demo');
    $denied = User::create(['email' => 'b@demo.test', 'password' => bcrypt('secret')]);

    ArkheNav::section('perm', heading: 'Perm')
        ->item('demo', 'Demo', 'star', route: 'arkhe.users.index', can: 'view-demo');

    expect(collect(ArkheNav::sectionsFor($allowed))->pluck('key'))->toContain('perm')
        ->and(collect(ArkheNav::sectionsFor($denied))->pluck('key'))->not->toContain('perm');
});

it('resolves closure labels and headings lazily', function (): void {
    ArkheNav::section('lazy', heading: static fn (): string => 'Heading '.app()->getLocale())
        ->item('it', static fn (): string => 'Label', 'star', route: 'arkhe.users.index');

    $section = ArkheNav::section('lazy');

    expect($section->heading())->toStartWith('Heading ')
        ->and($section->visibleItems(null)[0]->label())->toBe('Label');
});
