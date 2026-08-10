<?php

declare(strict_types=1);

use Arkhe\Main\Livewire\Cookies;
use Arkhe\Main\Livewire\EditRole;
use Arkhe\Main\Livewire\EditUser;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Livewire\Sitemap;
use Arkhe\Main\Livewire\SiteSeo;
use Arkhe\Main\Livewire\StrongAuthRequired;
use Illuminate\Support\Facades\Route;

$middleware = (array) config('arkhe.middleware');

// Resolve every Livewire page from the `arkhe.components` map so a host app
// can route to its own subclass without redeclaring any of these routes.
// Falls back to the package's default class when no override is set.
$component = static fn (string $alias, string $default): string => (string) config("arkhe.components.{$alias}", $default);

// Pas de route de tableau de bord : la page d'accueil du back-office
// appartient à l'app, pas au paquet. Les starter kits en fournissent une,
// prête à recevoir les indicateurs qui comptent pour elle — Arkhe n'a pas à
// la remplacer par ses propres compteurs d'utilisateurs, que la liste des
// utilisateurs affiche déjà en tête.

// The interstitial the strong-auth gate redirects to. It sits OUTSIDE the
// guarded group on purpose: guarded, it would block the very page that explains
// the block and bounce the user between the two forever. `auth` alone, since
// there is nothing sensitive on it — it tells an already-authenticated user
// what to enrol, and nothing more.
//
// It stays registered even when enforcement is off: a stale link or a browser
// back button should find a page rather than a 404, and it costs nothing.
Route::middleware(['web', 'auth'])
    ->prefix((string) config('arkhe.admin.prefix', config('arkhe.route_prefix', 'administration')))
    ->name('arkhe.')
    ->group(function () use ($component): void {
        Route::get('/strong-auth', $component('strong-auth-required', StrongAuthRequired::class))
            ->name('strong-auth.required');
    });

// Appended to the configured stack rather than declared in the `arkhe.middleware`
// default: an app that published its config before this release carries a frozen
// `['web', 'auth', 'arkhe.backend']` on disk, and a new package default would
// never reach it. Appended here, it reaches everyone — and it stays inert until
// `arkhe.strong_auth.enforce` is switched on, which it is not by default.
//
// It goes last, and in the same array: a second chained `->middleware()` call
// REPLACES the group's stack instead of extending it, which would silently drop
// `auth` and `arkhe.backend` and leave the backend wide open. Last also means
// `auth` has already run, so the gate always sees a resolved user.
//
// One application covers the whole backend, nested groups included — the
// requirement is all-or-nothing, so the sensitive area needs no second pass.
$middleware[] = 'arkhe.strong-auth';

Route::middleware($middleware)
    ->prefix((string) config('arkhe.admin.prefix', config('arkhe.route_prefix', 'administration')))
    ->name('arkhe.')
    ->group(function () use ($component): void {
        Route::get('/users', $component('list-users', ListUsers::class))->name('users.index');
        Route::get('/users/create', $component('edit-user', EditUser::class))->name('users.create');
        Route::get('/users/{user}/edit', $component('edit-user', EditUser::class))->name('users.edit');

        // Roles + permissions + site SEO are restricted to the `root` role.
        // The strong-auth gate already ran on the outer group and covers these
        // too, so only the permission check belongs here. It therefore answers
        // after the gate: a factorless user is sent to enrol without learning
        // whether this area exists or whether they could enter it.
        Route::middleware('arkhe.root')->group(function () use ($component): void {
            // Pas de route de création : les rôles viennent de
            // `config('arkhe.roles')` et du seeder, parce que le code s'y
            // réfère (middlewares, `isArkheRoot()`). La fiche sert à régler
            // leurs permissions, pas à en fabriquer.
            Route::get('/roles',             $component('list-roles', ListRoles::class))->name('roles.index');
            Route::get('/roles/{role}/edit', $component('edit-role',  EditRole::class))->name('roles.edit');

            // Les permissions se gèrent depuis la page des rôles depuis la 3.3 :
            // la route survit en redirigeant, pour ne pas casser les
            // `route('arkhe.permissions.index')` des apps consommatrices.
            // À retirer à la prochaine majeure (cf. UPGRADE.md).
            Route::redirect('/permissions', '/'.trim((string) config('arkhe.admin.prefix', 'administration'), '/').'/roles')
                ->name('permissions.index');

            Route::get('/seo',         $component('site-seo',   SiteSeo::class))->name('site-seo.edit');
            Route::get('/sitemap',     $component('sitemap',    Sitemap::class))->name('sitemap.edit');
            Route::get('/cookies',     $component('cookies',    Cookies::class))->name('cookies.index');
        });
    });
