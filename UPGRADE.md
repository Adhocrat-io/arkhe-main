# Upgrade Guide

This document tracks breaking and behavioural changes between major versions of `adhocrat-io/arkhe-main`.

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
