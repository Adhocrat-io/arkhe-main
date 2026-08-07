# Upgrade Guide

This document tracks breaking and behavioural changes between major versions of `adhocrat-io/arkhe-main`.

## Vers la 3.3 — rôles et permissions réunis

La liste des permissions n'existe plus en tant que page : les permissions se
consultent et s'attachent depuis `/administration/roles`, renommée
« Rôles & permissions ».

Rien n'est à faire pour la plupart des apps. Deux points de vigilance :

1. **`route('arkhe.permissions.index')` continue de résoudre**, mais redirige
   vers la page des rôles. Le nom de route et le composant
   `Arkhe\Main\Livewire\ListPermissions` seront retirés à la prochaine
   majeure — remplacez vos liens par `route('arkhe.roles.index')` dès que
   possible.

2. **Barre latérale surchargée.** Si vous avez publié
   `resources/views/vendor/arkhe/partials/sidebar-items.blade.php`, votre copie
   contient encore une entrée « Permissions ». Elle reste fonctionnelle (le lien
   redirige), mais fait doublon avec « Rôles » : retirez-la de votre copie.

Si votre app tient à conserver un écran de gestion des permissions, montez
`ListPermissions` sur une route à vous — le composant reste enregistré sous
l'alias Livewire `arkhe.list-permissions` :

```php
Route::get('/administration/permissions', \Arkhe\Main\Livewire\ListPermissions::class)
    ->middleware(config('arkhe.middleware'))
    ->name('admin.permissions.index');
```

## From `dev-main` to `v0.1.0` (phase 1)

There is nothing to upgrade — `v0.1.0` will be the first tagged release.

When upgrading consumer apps from `dev-main` to `v0.1.0`:

1. Bump the constraint in `composer.json`:

   ```json
   {
       "require": {
           "adhocrat-io/arkhe-main": "^0.1.0"
       }
   }
   ```

2. Republish (and re-merge) the config to pick up any tweaked defaults:

   ```bash
   php artisan vendor:publish --tag=arkhe-config --force
   ```

3. No migration changes between `dev-main` and `v0.1.0`.

## Conventions

- One **minor** version per new Laravel major supported (Laravel 14 → Arkhe 0.2.x, etc.).
- Breaking changes that aren't tied to a Laravel upgrade ship in a **major** version.
- Each release entry below should list: required commands, config keys added/removed, public API changes, deprecations.
