# Changelog

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
