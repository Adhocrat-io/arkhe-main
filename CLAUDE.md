# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Arkhè Main** (`adhocrat-io/arkhe-main`) is a Laravel package providing foundational user, role, and permission management for CMS applications. It follows clean architecture principles with Repository pattern, DTOs, and event-driven design.

## Development Commands

```bash
# Run all tests
./vendor/bin/pest

# Run tests with compact output
./vendor/bin/pest --compact

# Run specific test file
./vendor/bin/pest tests/Unit/Repositories/UserRepositoryTest.php

# Run tests matching a filter
./vendor/bin/pest --filter=UserRepository

# Format code (required before commits)
./vendor/bin/pint --dirty
```

## Architecture

### Package Structure

```
src/
├── ArkheMainServiceProvider.php   # Registers components, policies, routes
├── Repositories/                   # Data access layer (UserRepository, RoleRepository)
├── DataTransferObjects/            # Immutable DTOs for data transfer
├── Policies/                       # Authorization (UserPolicy, RolePolicy)
├── Enums/Users/UserRoleEnum.php   # Role hierarchy enum
├── Events/                         # Domain events (UserCreated, RoleUpdated, etc.)
├── Livewire/Admin/Users/          # Admin UI components
└── Console/Commands/               # Artisan commands

stubs/                              # Publishable files for consuming applications
config/arkhe.php                    # Role/permission configuration
```

### Key Patterns

**Repository Pattern**: All database operations go through repositories (`UserRepository`, `RoleRepository`). Never access models directly for mutations.

**DTOs**: Use `UserDto` and `RoleDto` for passing data to repositories. Arrays are discouraged for data transfer.

**Events**: Repositories dispatch events (`UserCreated`, `UserUpdated`, `UserDeleted`, etc.) after database transactions.

**Authorization**: Policies (`UserPolicy`, `RolePolicy`) handle all authorization. Use `Gate::policy()` registration.

### Role Hierarchy

Seven roles with hierarchical permissions defined in `UserRoleEnum`:
- `ROOT` - Can manage all users/roles including other ROOT users
- `ADMIN` - Can manage all non-ROOT users
- `EDITORIAL` - Can manage EDITORIAL and AUTHOR users
- `AUTHOR`, `CONTRIBUTOR`, `SUBSCRIBER`, `GUEST` - Limited management scope

Protected roles (ROOT, ADMIN) cannot be deleted.

### Livewire Components

Components registered with prefix `arkhe.main.livewire.*`:
- `admin.users.users-list` - User management list with search/filter
- `admin.users.users-create` / `users-edit` - User CRUD forms
- `admin.users.roles.roles-list` / `role-edit` - Role management (ROOT only)

## Configuration

`config/arkhe.php` defines:
- `admin.prefix` - Admin route prefix (env: `ARKHE_ADMIN_PREFIX`, default: `administration`)
- `admin.roles` - Roles allowed to access administration
- `permissions` - Permission groups (manage-users, manage-roles, etc.)
- `roles` - Role-to-permission mappings (supports wildcards with `*`)

## Testing

Tests use Orchestra Testbench with SQLite in-memory database. Test files in:
- `tests/Unit/` - Repositories, Policies, Enums
- `tests/Feature/` - Integration tests

Factories and fixtures available in `tests/Fixtures/`.

## Installation (for consuming apps)

```bash
php artisan arkhe-main:install              # Interactive installation wizard
```

## Code Conventions

- Strong typing with explicit return types on all methods
- Constructor property promotion (PHP 8 style)
- PHPDoc blocks over inline comments
- Enum keys in TitleCase
- Models handle only database modeling and relations
- Controllers/Livewire handle presentation only
- Services handle business logic
- Repositories handle database access
