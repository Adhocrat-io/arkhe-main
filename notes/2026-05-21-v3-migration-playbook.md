# Arkhe Main — playbook de migration V2 → V3 par app

> Snapshot du 2026-05-21. Référence pour la migration progressive des
> consommateurs d'Arkhe Main d'une génération à l'autre, sans coupure.

## Rappel — pourquoi V3 a pu garder la continuité

V3 a été refactorée pour :
- **garder le namespace `Arkhe\Main\`** (donc tous les `use Arkhe\Main\…` des
  apps continuent de résoudre) ;
- **garder le provider `Arkhe\Main\ArkheMainServiceProvider`** (donc
  `composer.json` côté apps n'a rien à toucher pour la découverte) ;
- **garder les préfixes de config V2** (`admin.prefix`, `admin.layout`,
  `admin.roles`, top-level `permissions`/`roles`/`role_hierarchy`/`role_labels`).

V3 ajoute par-dessus :
- les hooks lifecycle (`beforeSave`, `afterCreate`, `afterUpdate`, `beforeDelete`)
  sur chaque page Livewire ;
- les composants rebindables via `config('arkhe.components')` ;
- un middleware `arkhe.backend` permission-based avec fallback role-based
  (compat V2) ;
- une commande `arkhe:main:upgrade-from-v2` qui ajoute les clés V3 manquantes
  au `config/arkhe.php` du consommateur, sans toucher aux clés V2.

## État des consommateurs au 2026-05-21

| App         | Contrainte composer | Lock ref       | Action immédiate    |
|-------------|---------------------|----------------|---------------------|
| petitionca  | `^2.0`              | tag 2.0.0      | rien                |
| ranadh      | `^2.0`              | tag 2.0.0      | rien                |
| agem        | `^2.0` *(gelé)*     | b8a54ec        | rien                |
| rc          | `^2.0` *(gelé)*     | b8a54ec        | rien                |
| pfefond     | `^2.0` *(gelé)*     | dd0169f        | rien                |
| rcdons      | `^2.0` *(gelé)*     | f70a7cf        | rien                |
| test        | `dev-main` *(path)* | symlink local  | suit le package     |

Le gel sur `^2.0` empêche un futur tag `3.0.0` de migrer une app par accident.

## Quand publier la V3 = tag 3.0.0

Pré-requis :
- [x] namespace `Arkhe\Main` rétabli
- [x] provider `ArkheMainServiceProvider` rétabli
- [x] config aux préfixes V2
- [x] suite verte (12 fichiers PASS, > 130 tests)
- [ ] manuel : `CHANGELOG.md` rédigé pour le 3.0.0
- [ ] manuel : `composer require spatie/laravel-permission:^7.0` vérifié sur
  une app pilote

Quand tu pousses le tag, les 6 consommateurs gelés restent sur 2.x. Aucun
risque.

## Recette d'upgrade — la même pour les 6 apps

L'ordre conseillé : commencer par **petitionca** (la plus petite surface,
config V2 quasi vanilla) pour valider la recette, puis enchaîner.

### 1. Préparer un branch d'upgrade

```bash
cd /Volumes/BOULOT/Sites/<app>
git checkout -b arkhe/v3-upgrade
```

### 2. Bumper la contrainte

```bash
# dans composer.json :  "adhocrat-io/arkhe-main": "^2.0"
#                       "adhocrat-io/arkhe-main": "^3.0"
composer update adhocrat-io/arkhe-main spatie/laravel-permission --with-all-dependencies
```

`spatie/laravel-permission` bascule de `^6` à `^7`. Lire le changelog Spatie
6 → 7 — pour la plupart des apps c'est transparent (renommage de quelques
méthodes internes, tables compatibles).

### 3. Faire jouer la commande d'upgrade

```bash
php artisan arkhe:main:upgrade-from-v2 --dry-run   # rapport
php artisan arkhe:main:upgrade-from-v2             # write
```

La commande :
- ajoute les clés V3 manquantes (`dashboard_route`, `role_permissions`,
  `components`, `backend_permission`, `root_permission`, `features`…) à
  `config/arkhe.php` sans toucher aux clés V2 ;
- réécrit les anciens aliases Livewire dans les blades :
  `arkhe.main.livewire.admin.users.users-list` → `arkhe.list-users` etc. ;
- prévient si `composer.json` pinne `spatie/laravel-permission` sur ^6.

### 4. Migration BDD et seed

```bash
php artisan migrate                  # idempotente, ajoute les colonnes manquantes
php artisan db:seed --class=Arkhe\\Main\\Database\\Seeders\\ArkheRolesSeeder
```

Le seeder crée les permissions V3 (`access-backend`, `manage-users`,
`view-user`, …) **sans toucher** aux permissions custom déjà présentes.

### 5. Vérifier les overrides Livewire

Si l'app a des sous-classes (ex. `App\Livewire\Admin\Users\ListUsers extends
…`), pointer la config vers elles :

```php
// config/arkhe.php
'components' => [
    'list-users' => App\Livewire\Admin\Users\ListUsers::class,
],
```

V2 utilisait `UsersList` + `UserCreate` + `UserEdit` séparés ; V3 a une seule
page CRUD `ListUsers`. Si l'app surchargeait `UsersList` pour ajouter des
boutons, refactorer pour étendre `Arkhe\Main\Livewire\ListUsers` + utiliser
les hooks lifecycle.

### 6. Smoke-tester l'admin

- `/{admin.prefix}/users` → liste, création, édition, suppression
- `/{admin.prefix}/roles` → root uniquement
- `/{admin.prefix}/permissions` → root uniquement
- Login → vérifier que la redirection post-login pointe sur l'admin si
  `dashboard_route` est set

### 7. Merger + déployer

Si tout est vert : merger la branche, déployer, et passer à l'app suivante.

## Souplesses ajoutées (lever 6, etc.)

À ouvrir dès qu'un cas concret arrive :

- **Slot `<x-arkhe::form-extra-fields>` (lever 6, ouvert)** — pour les apps
  qui veulent ajouter un champ au form sans forker la vue.
- **Compat des aliases Livewire** — pour l'instant la commande réécrit
  proactivement. Si tu préfères un filet de sécurité runtime, ajouter dans
  `ArkheMainServiceProvider::packageBooted` :

  ```php
  foreach ([
      'arkhe.main.livewire.admin.users.users-list' => 'list-users',
      // …
  ] as $oldAlias => $newKey) {
      Livewire::component($oldAlias, config("arkhe.components.{$newKey}"));
  }
  ```

## Suivi

- Marquer chaque app comme "migrée V3" dans ce playbook après merge :
  - [ ] petitionca
  - [ ] ranadh
  - [ ] agem
  - [ ] rc
  - [ ] pfefond
  - [ ] rcdons
