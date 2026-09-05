# Changelog

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
