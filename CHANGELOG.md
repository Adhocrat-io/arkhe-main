# Changelog

All notable changes to `adhocrat-io/arkhe-main` are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `arkhe:main:install` now offers to patch the consumer's Tailwind v4
  `resources/css/app.css` with the `@source` directive needed to scan the
  package's Blade views. Idempotent. Falls back to a printed snippet for
  Tailwind v3 setups (`tailwind.config.js`).

### Changed
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
