# Dependency collision audit

The runtime keeps Illuminate dependencies in its private package `vendor/` tree and loads that tree only from `Runtime::boot()`. The development lock resolves the following runtime set:

| Package group | Resolved versions |
| --- | --- |
| `illuminate/container`, `contracts`, `database`, `collections`, `conditionable`, `macroable`, `support` | `v11.51.0` |
| `brick/math` | `0.14.1` |
| `carbonphp/carbon-doctrine-types` | `3.2.0` |
| `nesbot/carbon` | `3.13.2` |
| `laravel/serializable-closure` | `v2.0.15` |
| `doctrine/inflector` | `2.1.0` |
| `psr/container` | `2.0.2` |
| `psr/simple-cache` | `3.0.0` |
| `psr/clock` | `1.0.0` |
| `symfony/clock` | `v7.4.8` |
| `symfony/deprecation-contracts` | `v3.6.0` |
| `symfony/polyfill-mbstring`, `polyfill-php83` | `v1.38.2`, `v1.41.0` |
| `symfony/translation`, `translation-contracts` | `v7.4.16`, `v3.7.1` |
| `voku/portable-ascii` | `2.0.3` |

The inspected vBulletin Composer lock has overlapping names including `brick/math`, `doctrine/inflector`, `psr/clock`, `symfony/deprecation-contracts`, and `voku/portable-ascii`. The runtime pins those overlapping packages to the versions observed in the vBulletin lock. No Illuminate packages were present in the inspected vBulletin dependency set.

The private vendor tree reduces coupling and prevents the two projects from sharing a Composer vendor directory, but it is not namespace isolation: both autoloaders still run in one PHP process. A future dependency with incompatible shared namespaces may require build-time prefixing such as PHP-Scoper. No such conflict has been observed in the validated 0.1.0 dependency set, so PHP-Scoper is intentionally not introduced now.
