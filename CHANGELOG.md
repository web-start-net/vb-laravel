# Changelog

## 0.3.0 - 2026-10-14

- **Developer & AI Experience Suite**:
  - `vbl make:addon <id> --title="..."`: Full scaffolding of new add-ons with zero edits required to validate and build.
  - `vbl make:model|migration|hook|api|template <name>`: Granular artifact generators.
  - `vbl dev <addon> --target=<vb core dir>`: Automated build, package deployment, and XML re-import into vBulletin.
  - `vbl package <addon>`: Distribution packaging producing upload-ready zip archives for forum administrators.
  - `vbl release [version]`: Single source of truth sync eliminating package drift between `src/` and distribution package.
- **Enhanced Security & Gateway CSRF**:
  - Relied on vBulletin 6 native CSRF verification for all `POST /ajax/api` calls (`vB5_Frontend_ApplicationLight::execute()` -> `vB_Api_State::checkCSRF()`).
  - Added `guest: false` default on `#[Api(write: true)]` to automatically reject unauthenticated requests (`userid === 0`).
  - Added strict rule: never invoke `write: true` APIs from template `{vb:data}` calls.
- **AST Compiler & Fidelity**:
  - Converted metadata analysis to `nikic/php-parser` AST discovery, preserving exact method signatures and default arguments.
  - Standardized vBulletin Settings XML with `<options><settinggroup>` and `vbsettings` phrases.
  - Read-only Eloquent models (`User`, `Node`, `Channel`) guarded with custom `ReadOnlyBuilder` blocking writes.
- **GitHub Actions CI**: Added automated matrix CI on PHP 8.2 & 8.3 with linting and unit/integration test suites.

## 0.2.0 - 2026-09-05

- **Centralized Eloquent Database Booting**: `Runtime::bootDatabase()` now automatically configures and boots Eloquent via `vB::getConfig()` with `utf8mb4` encoding, eliminating duplicate database connections across add-ons.
- **Dynamic PSR-4 Autoloading**: `AddonLoader` automatically registers a PSR-4 autoloader for every loaded add-on, eliminating the need for manual `require_once` statements.
- **Global Developer Helpers**: Added `collect()`, `app()`, and `db()` global helpers.
- **Enhanced Bridge API**: Added `vb()->userId()`, `vb()->isLoggedIn()`, `vb()->setting()`, and `vb()->session()` methods to the `Bridge`.
- **Improved Backward Compatibility**: Supported semver range matching for `^0.1` and `^0.2` add-ons.

## 0.1.0 - 2026-08-11

- First experimental release of VB Laravel for vBulletin 6.
- Added the lightweight `vb()` bridge and explicit, lazy Illuminate boot.
- Added Eloquent support without claiming a full Laravel application stack.
- Added `vb-addon.json` and the deterministic Add-on Loader.
- Centralized Illuminate dependencies in the Runtime's private vendor tree.
- Made no modifications to the vBulletin core.
- Added runtime-focused regression checks.
- Validated the Runtime integration lifecycle on vBulletin 6.2.2.

This is an experimental 0.x release. Compatibility and public APIs may change
in a future minor release before 1.0.0.
