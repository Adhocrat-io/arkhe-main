# Changelog

All notable changes to `adhocrat-io/arkhe-main` are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial package skeleton based on `spatie/laravel-package-tools`.
- `ArkheServiceProvider` with config, views, translations, route and migration auto-discovery.
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

[Unreleased]: https://github.com/adhocrat-io/arkhe-main/compare/main...HEAD
