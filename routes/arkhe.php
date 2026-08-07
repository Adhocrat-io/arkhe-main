<?php

declare(strict_types=1);

use Arkhe\Main\Livewire\EditRole;
use Arkhe\Main\Livewire\EditUser;
use Arkhe\Main\Livewire\ListRoles;
use Arkhe\Main\Livewire\ListUsers;
use Arkhe\Main\Livewire\Cookies;
use Arkhe\Main\Livewire\SiteSeo;
use Arkhe\Main\Livewire\Sitemap;
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

Route::middleware($middleware)
    ->prefix((string) config('arkhe.admin.prefix', config('arkhe.route_prefix', 'administration')))
    ->name('arkhe.')
    ->group(function () use ($component): void {
        Route::get('/users', $component('list-users', ListUsers::class))->name('users.index');
        Route::get('/users/create', $component('edit-user', EditUser::class))->name('users.create');
        Route::get('/users/{user}/edit', $component('edit-user', EditUser::class))->name('users.edit');

        // Roles + permissions + site SEO are restricted to the `root` role.
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
