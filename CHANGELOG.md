# Changelog

All notable changes to `adhocrat-io/arkhe-main` are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

Quatre élévations de privilèges, toutes de la même famille : **on pouvait
accorder ce qu'on ne détenait pas**. Chacune permettait à un compte du
back-office de devenir root. Elles sont fermées au niveau des *services*, donc
sur tous les chemins d'écriture — composants, méthodes dépréciées,
sous-classes, commandes.

- **Permissions d'un rôle.** `update-role` suffisait à s'attribuer
  `manage-roles` en éditant son propre rôle. `RoleService` n'accorde plus
  qu'une permission que l'acteur détient lui-même.
- **Permissions directes d'un utilisateur.** Le champ `permissions` de
  `UserForm` n'était pas gardé, alors que les rôles voisins l'étaient :
  `create-user` suffisait à fabriquer un compte porteur de `manage-roles`,
  puis à s'y connecter. `UserService` applique désormais la même règle.
- **Rôles hors hiérarchie.** Un rôle absent de `config('arkhe.roles')` avait
  le rang `-1`, comme un utilisateur sans rôle : `-1 <= -1` ouvrait son
  attribution à quiconque, quelles que soient les permissions qu'il portait.
  `RoleHierarchy` distingue maintenant « aucun rôle » de « rôle inconnu » : un
  rôle non classé n'est attribuable que par qui détient déjà tout ce qu'il
  accorde. `canManage()` avait le même angle mort.
- **Renommage d'une permission.** Le plus retors, car il contournait les trois
  gardes précédentes d'un coup : les tables pivots référencent l'identifiant,
  pas le nom. Rebaptiser `view-user` en `manage-roles` transformait
  instantanément tous ses porteurs en administrateurs, sans toucher à un rôle
  ni à un compte. `PermissionService` refuse désormais de renommer ou
  supprimer une permission canonique — même pour root, le code du paquet s'y
  réfère en dur — et n'autorise à altérer que ce que l'acteur détient.

Durcissements associés :

- `#[Locked]` sur les propriétés qui portent l'identité de l'objet édité
  (`EditRole::$roleId`, `$isCanonical`, `EditUser::$userId`) : sans lui, le
  client les réécrivait avant `save()` et pivotait vers une autre cible, les
  `authorize()` ne portant que sur la permission, jamais sur *quoi*.
- `RoleForm` relit le caractère canonique depuis la base au lieu de croire son
  propre drapeau public : forcé à `true`, il faisait tomber *toutes* les règles
  du nom, unicité comprise.
- `EditRole::toggleGroup()`, publique donc appelable directement, porte sa
  propre autorisation.
- Les noms de rôles qui valent l'accès au back-office par eux-mêmes — ceux
  listés bruts dans `arkhe.admin.roles`, chemin de compatibilité V2 — sont
  réservés à la création : fabriquer un rôle homonyme et se l'attribuer
  ouvrait la porte sans détenir `access-backend`. Le repli du middleware, lui,
  est **inchangé** : le durcir aurait privé d'accès les apps qui s'en servent,
  à la simple montée de version.
- Le tri des listes est filtré côté composant *et* côté repository — la garde
  ne repose plus sur une implémentation que l'app hôte peut remplacer.
- `ListPermissions` exige la permission de zone sensible : il reste montable
  sur une route applicative et donne accès au socle du RBAC.

Vingt tests de non-régression couvrent ces chemins
(`tests/Feature/PrivilegeEscalationTest.php`).

### Fixed
- **Création d'utilisateur impossible depuis l'interface.** `passwordConfirmation`
  ne faisait pas partie du contrat sérialisé de `UserForm` : Livewire ne la
  restituait pas au tour suivant, si bien que la confirmation était comparée à
  une chaîne vide et qu'une paire pourtant identique était refusée. Le défaut
  touchait aussi l'ancien flyout ; aucun test ne le couvrait, les suites
  existantes appelant `UserService` directement.

### Changed
- **Refonte des pages de liste.** `list-users` et `list-roles` adoptent le
  langage visuel des back-offices maison : en-tête titre + description,
  compteurs de tête, table encadrée à lignes zébrées avec en-têtes triables
  et overlay pendant les requêtes, états vides qui distinguent « rien à
  afficher » de « rien qui corresponde aux filtres », et toasts sur chaque
  action. Six composants Blade partagés (`x-arkhe::page-header`, `stat-bar`,
  `list-table-wrapper`, `sortable-header`, `table-empty-state`,
  `confirm-modal`) portent ce langage et sont réutilisables par les paquets
  satellites.
- **Rôles et permissions réunis sur une page.** `/administration/roles`
  devient « Rôles & permissions » : chaque ligne donne le libellé du rôle,
  son identifiant et le nombre de permissions qu'il porte. Les filtres et le
  tri sont persistés dans l'URL sur les deux listes.
- **Création et édition sur leur propre page.** Les flyouts laissent place à
  trois routes — `arkhe.users.create`, `arkhe.users.edit`, `arkhe.roles.edit` —
  servies par les composants `EditUser` et `EditRole`, surchargeables via
  `config('arkhe.components')` comme les listes. Le formulaire y est découpé en
  sections (identité, photo, sécurité, accès), chaque champ portant sa
  description.
- **Les rôles ne se créent ni ne se suppriment plus depuis l'interface.** Ils
  sont déclarés dans `config('arkhe.roles')` et créés par `ArkheRolesSeeder` :
  le code en fait foi, pas un écran. La liste n'offre plus « Créer un rôle » ni
  « Supprimer », et sa colonne Actions mène directement à la fiche, qui sert à
  régler les permissions. Côté service, rien ne change :
  `RoleService::create()` / `delete()`, les événements `RoleCreated` /
  `RoleDeleted` et les permissions `create-role` / `delete-role` restent en
  place pour les seeders et les commandes.
- **Zone de dépôt pour la photo de profil.** L'`<input type="file">` brut, seul
  endroit des fiches qui échappait au langage Flux, laisse place à
  `x-arkhe::image-upload` : aperçu de ce qu'on vient de déposer, glisser-déposer
  avec retour visuel, indicateur pendant le téléversement. Le composant est
  partagé — toute page ayant une image à recevoir peut s'en servir.
- **Une photo enregistrée peut enfin être retirée.** Jusqu'ici elle ne pouvait
  que se remplacer. Le retrait est différé : marqué à l'écran, appliqué à
  l'enregistrement, annulable avant. Déposer une nouvelle photo annule le
  retrait — on ne supprime pas ce qu'on vient de remplacer.
- **Chaque champ des formulaires porte sa description.** Au-delà de l'aide
  qu'elle apporte, c'est ce qui aligne deux champs voisins dans une grille : un
  champ décrit à côté d'un champ nu poussait son contrôle vers le bas.
- **Les permissions d'un rôle se cochent par ressource.** La fiche d'un rôle
  range les permissions en cartes (`users`, `roles`, `sitemap`…) avec un
  « tout cocher » par groupe, plutôt qu'une liste à choix multiple de plusieurs
  dizaines de lignes. Le regroupement est déduit de la convention de nommage
  `<verbe>-<ressource>` ; une app peut l'imposer via
  `config('arkhe.permission_groups')`. Un rôle canonique garde son nom et son
  guard verrouillés, mais ses permissions restent modifiables.

### Deprecated
- Les méthodes de formulaire des composants de liste (`openCreate`,
  `openEdit`, `save` et les hooks `beforeSave` / `afterCreate` / `afterUpdate`
  sur `ListUsers` et `ListRoles`). Elles ne sont plus appelées par les vues du
  paquet — les pages dédiées les remplacent — mais restent en place pour les
  sous-classes qui les surchargent. Retrait à la prochaine majeure : portez
  vos surcharges sur `EditUser` / `EditRole`, qui exposent les mêmes hooks aux
  mêmes signatures. Seule exception, `afterCreate` sur les rôles : il n'a plus
  d'équivalent actif, la création ayant quitté l'interface.
- `ListRoles::confirmDelete()`, `delete()`, `cancelDelete()` et le hook
  `beforeDelete` : la suppression d'un rôle a quitté l'interface. Le paquet ne
  les appelle plus ; elles restent pour les sous-classes et partiront à la
  prochaine majeure.
- La page de liste des permissions. `/administration/permissions` redirige
  désormais vers `/administration/roles` ; le nom de route
  `arkhe.permissions.index` et le composant `Arkhe\Main\Livewire\ListPermissions`
  restent en place pour ne rien casser, mais seront retirés à la prochaine
  majeure. L'entrée « Permissions » disparaît de la barre latérale, l'entrée
  « Rôles » couvrant les deux.

### Added
- `arkhe:main:upgrade-from-v2` now rewrites the V2 `roles` / `permissions`
  config layout into the V3 one: `roles` becomes a key => name map,
  `permissions` a flat deduplicated list, and the V2 role => permissions
  mapping moves **verbatim** (enum keys and inline comments preserved) into a
  new `role_permissions` entry. The rewrite is tokenizer-based, only touches
  the two top-level entries, prompts before writing and honours `--dry-run`.

### Changed
- `ArkheRolesSeeder` (and therefore `arkhe:main:install`) now fails fast with
  an actionable message when `config/arkhe.php` still uses the V2
  roles/permissions layout, instead of crashing mid-insert with an opaque
  `Array to string conversion` SQL error.

## [3.2.1] — 2026-06-18

### Documentation
- Clarify in the `ArkheNav` guide that a menu `can` only controls link
  *visibility* — package routes must still be protected by middleware
  independently. The menu gate and the route guard are two separate layers.

## [3.2.0] — 2026-06-18

### Added
- **Shared navigation registry** (`Arkhe\Main\Support\ArkheNav`, with
  `NavSection` / `NavItem`). The backend sidebar is now driven by named
  sections so satellite packages can branch onto the same menu — add an entry
  to the shared `settings` ("Réglages") section, or declare their own
  collapsible section — without patching any Blade file. Arkhè seeds two default
  sections: `access` ("Accès": users, roles, permissions) and `settings`
  ("Réglages": SEO, sitemap, cookies). Example:

  ```php
  use Arkhe\Main\Support\ArkheNav;

  ArkheNav::section('settings')->item(
      key: 'billing', label: fn () => __('billing::nav.title'),
      icon: 'credit-card', route: 'billing.settings', can: 'manage-billing',
  );
  ```

- `arkhe::arkhe.settings.title` translation ("Réglages" / "Settings") for the
  consolidated settings section heading.

### Changed
- `arkhe::partials.sidebar-items` now renders from the registry instead of a
  hardcoded item list. The default menu is unchanged; the `@include` host apps
  already have keeps working as-is. Roles/permissions/settings visibility is now
  gated by the `root_permission` (matching the route guards) rather than the
  `root` *role*, so a custom role granted `manage-roles` sees those items too.

## [3.1.0] — 2026-06-05

### Added
- `whitecube/laravel-cookie-consent` (`^1.3`) integration for GDPR cookie
  consent. `Arkhe\Main\Cookies\ArkheCookiesServiceProvider` pre-registers
  Laravel's session + CSRF cookies under "essentials", and the layout
  renders `@cookieconsentscripts` / `@cookieconsentview` automatically
  when `Features::hasCookieConsent()` is true (now the default).
  Root-only read-only audit page at `/administration/cookies` (Livewire
  alias `arkhe.cookies`, route `arkhe.cookies.index`) lists every
  registered category/cookie. New permission `view-cookies`.
- `spatie/laravel-sitemap` (`^8.1`) integration. `Arkhe\Main\Services\SitemapService`
  wraps `SitemapGenerator` with config-driven URL/path; a scheduled
  `Arkhe\Main\Jobs\GenerateSitemap` job runs daily at the cron expression
  set by `arkhe.sitemap.schedule` (defaults `0 3 * * *`); the root-only
  Livewire page at `/administration/sitemap` exposes a "Regenerate now"
  button that dispatches the same job onto the host app's queue. Last
  generation timestamp persisted on the `arkhe_site_seo` table via a new
  `sitemap_generated_at` column. Three new permissions
  (`manage-sitemap`, `view-sitemap`, `update-sitemap`).
- `ralphjsmit/laravel-seo` (`^1.8`) is now a first-class dependency. The
  package's `seo()` helper is rendered in the Arkhe layout's `<head>` so
  every backend page emits sensible meta tags out of the box. See the new
  [SEO section](README.md#seo) in the README.
- `Arkhe\Main\Concerns\HasArkheSeo` trait — composes `HasSEO` behind an
  Arkhe-namespaced alias so consumers add per-record SEO with a single
  `use` statement.
- Site-wide SEO admin UI at `/administration/seo`
  (Livewire alias `arkhe.site-seo`, route `arkhe.site-seo.edit`). Edits
  `arkhe_site_seo` (a singleton table holding `site_name`, `title_suffix`,
  `description`, `image`, `author`, `robots`, `twitter_username`,
  `favicon`). A `SEOManager::SEODataTransformer` registered in the
  service provider merges these defaults into every `SEOData` rendered
  through the layout. Root-only; gated by the new `manage-site-seo`,
  `view-site-seo`, `update-site-seo` permissions.
- `arkhe:main:install` now offers to publish `ralphjsmit/laravel-seo`'s
  migration + config when the `seo` table is missing.
- `arkhe:main:install` now offers to patch the consumer's Tailwind v4
  `resources/css/app.css` with the `@source` directive needed to scan the
  package's Blade views. Idempotent. Falls back to a printed snippet for
  Tailwind v3 setups (`tailwind.config.js`).

### Changed
- `Features::hasSeo()` and `Features::hasCookieConsent()` now default to
  `true`. Both became first-class features in 3.1.0; the flags remain as
  escape hatches for consumers that want to disable an integration without
  removing the dependency.
- `arkhe:main:install` now patches the `HasBackendProfile` trait into the
  consumer's `App\Models\User` **before** prompting for the root user's
  credentials, and no longer requires a second run to finish creating the
  user. The model file is resolved via Composer's `ClassLoader` instead of
  Reflection so the class isn't autoloaded ahead of the patch.
- `list-users`, `list-roles` and `list-permissions` Livewire pages now use
  the `<flux:table>` primitives. Their CSS ships with `flux.css` (already
  imported by every consumer), so they render correctly in dark mode even
  if the consumer's Tailwind build doesn't `@source` the vendor path.

### Removed
- The unused `arkhe.install.patch_restart` translation key.

## [3.0.0] — 2026-05-21

V3 is a from-scratch rewrite that **keeps the V2 public surface** (namespace,
provider name, config prefixes) so existing host apps upgrade in place — no
search-replace of `use Arkhe\Main\…` statements required. The major bump
covers the bumped Spatie dependency (`^7`), the new permission-based RBAC,
the Livewire 4 page rewrites, and the lifecycle-hook extensibility layer.

### Added

- `arkhe:main:upgrade-from-v2` Artisan command — appends missing V3 keys
  (`dashboard_route`, `role_permissions`, `components`, `backend_permission`,
  `root_permission`, `features`…) to the host app's `config/arkhe.php`
  without disturbing V2 entries, and rewrites legacy Livewire aliases
  (`arkhe.main.livewire.admin.users.users-list` → `arkhe.list-users`) inside
  resources/views/. Supports `--dry-run`.
- Permission-based RBAC: 16 default permissions seeded by
  `ArkheRolesSeeder`, mapped to roles via `config('arkhe.role_permissions')`
  (use the literal `'*'` to grant every permission, typical for `root`).
- `EnsureUserIsRoot` middleware (alias `arkhe.root`) gating the sensitive
  zone (roles + permissions management) on the configurable
  `arkhe.root_permission` (defaults to `manage-roles`).
- `arkhe.backend` middleware now also accepts a role-list fallback through
  `config('arkhe.admin.roles')` — V2-shaped configs without seeded
  permissions keep working.
- Livewire pages are rebindable via `config('arkhe.components')`. Override
  any of `list-users`, `list-roles`, `list-permissions`, `dashboard` with a
  host-app subclass; routes auto-resolve to the configured class.
- Lifecycle hooks (`beforeSave`, `afterCreate`, `afterUpdate`,
  `beforeDelete`) on every Livewire CRUD page — for side-effects that need
  UI context (form state, flash messages) rather than a global event.
- `RoleHierarchy` support helper — derives the role hierarchy from the
  order of `config('arkhe.roles')` so renaming/inserting a role updates
  the seeder AND the assignment rules in one place.
- `ListRoles` + `ListPermissions` Livewire CRUD pages, restricted to the
  root area.
- `Dashboard` opt-in route registered when `ARKHE_DASHBOARD_ROUTE` is set,
  with optional Fortify `home` redirect override
  (`ARKHE_OVERRIDE_FORTIFY_REDIRECT`).
- `arkhe:main:add-user` CLI command for non-interactive user provisioning.
- Install command auto-patches the host app's sidebar with Arkhe links.
- `tests/Feature/ExtensibilityTest.php`, `PermissionSeederTest.php`,
  `ProfileMigrationSkipTest.php`, `UpgradeFromV2CommandTest.php` — extending
  coverage to ~78% of `src/`.

### Changed

- **BREAKING (build-time, not source-level)** — `spatie/laravel-permission`
  constraint bumped from `^6.25` to `^7.0`. Host apps should run
  `composer update spatie/laravel-permission --with-all-dependencies` during
  the V3 upgrade.
- `add_arkhe_profile_columns_to_users_table` migration is now idempotent
  column-by-column (`Schema::getColumnListing` + add only the missing ones,
  with a symmetric `down()`). Apps that already had `first_name`,
  `last_name`, `date_of_birth`, `civility` from V2 migrate cleanly.
- `ArkheRolesSeeder` is non-destructive — re-running it does not strip
  permissions the host app has granted to its own roles.
- Default layout switched from `arkhe::layouts.app` to `layouts::app` so
  the Livewire starter kit's sidebar wraps Arkhe pages out of the box. Set
  `ARKHE_ADMIN_LAYOUT=arkhe::layouts.app` to fall back to the package's
  minimal header-only layout.

### Compatibility

- **Namespace `Arkhe\Main\` preserved** — all `use Arkhe\Main\…` imports in
  host apps continue to resolve.
- **Provider `Arkhe\Main\ArkheMainServiceProvider` preserved** — composer's
  package discovery picks the V3 provider with no changes to host app
  `composer.json` `extra.laravel.providers`.
- **Config prefixes preserved** — V2 entries (`admin.prefix`, `admin.layout`,
  top-level `permissions`, `roles`, `role_hierarchy`, `role_labels`) keep
  working unchanged. V3 reads the new keys with V2 fallbacks
  (`arkhe.admin.prefix` falls back to `arkhe.route_prefix`,
  `arkhe.admin.layout` falls back to `arkhe.layout`).

## [2.0.0]

### Added

- Initial package skeleton based on `spatie/laravel-package-tools`.
- `ArkheMainServiceProvider` with config, views, translations, route and migration auto-discovery.
- Backend mounted at a configurable prefix (default: `/administration`).
- `EnsureUserHasBackendAccess` middleware aliased as `arkhe.backend`.
- `HasBackendProfile` trait (wraps `Spatie\Permission\Traits\HasRoles`) exposing `full_name`, `avatar_url`, `initials` and the helpers `isArkheRoot()` / `isArkheAdmin()`.
- `UserRepository` + `UserRepositoryInterface` contract.
- `UserService` for mutations (create/update/delete) with avatar upload, role and permission sync, dispatching `UserCreated` / `UserUpdated` / `UserDeleted` events.
- Livewire `ListUsers` full-page component with Flux UI Free (search, sort, filters, pagination, modal CRUD with avatar upload).
- `arkhe:main:install` interactive command (publishes config + migrations, seeds the four default roles, creates a root user).
- French translations (default) with English fallback.
- `Features::hasCookieConsent()` / `Features::hasSeo()` flags for phase 2.
- Pest 4 test suite covering install command, ListUsers CRUD/auth/search/sort, and `HasBackendProfile`.
- GitHub Actions matrix: PHP 8.3/8.4 × Laravel 12/13 × prefer-lowest/prefer-stable, with an optional dev-master job.

[Unreleased]: https://github.com/adhocrat-io/arkhe-main/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/adhocrat-io/arkhe-main/compare/v2.0.0...v3.0.0
[2.0.0]: https://github.com/adhocrat-io/arkhe-main/releases/tag/v2.0.0
