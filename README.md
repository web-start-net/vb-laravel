# VB Laravel

VB Laravel is a lightweight Illuminate-powered runtime and compatibility bridge that allows developers to build modern vBulletin 6 add-ons using familiar Laravel conventions without modifying the vBulletin core.

This is not a Laravel rewrite of vBulletin and not a separate Laravel application. It does not replace vBulletin authentication, routing, templates, permissions, users, phrases, Site Builder, or AdminCP. VB Laravel provides Illuminate capabilities for add-on business and data logic while vBulletin remains authoritative for native forum functionality.

## Why VB Laravel?

Traditional vBulletin add-on development requires learning many vBulletin-specific APIs and patterns. VB Laravel gives developers a simple mental model: `Laravel + vb()`.

Illuminate handles Eloquent, relationships, query building, collections, transactions, service/container logic, and compatible future Illuminate capabilities. `vb()` handles the current user, permissions, nodes/content, phrases, URLs, API access, and other native integrations.

## Architecture

```text
vBulletin 6
    |
    +-- Native vBulletin
    |     Users
    |     Permissions
    |     Routing
    |     Templates
    |     Phrases
    |     Site Builder
    |
    +-- VB Laravel Runtime
          |
          +-- Illuminate
          +-- AddonLoader
          +-- VB Bridge
                 |
                 +-- vb()
```

The package bootstrap registers `vb()` and the runtime classes but does not boot Illuminate. Normal vBulletin requests therefore do not load Illuminate unnecessarily. Call `Runtime::boot()` explicitly when an add-on needs it.

## Requirements

- vBulletin 6.2.2 has been tested.
- PHP 8.2 or newer is required.
- VB Laravel Runtime 0.1.0.
- Illuminate 11.x is the current baseline, controlled by the Runtime.

Version 0.1.0 is experimental. Compatibility and public APIs may change before 1.0.0.

## Installation

For a site administrator:

1. Upload `package/core/packages/vblaravel/` to `core/packages/vblaravel/` in the vBulletin installation.
2. Import the Runtime Product XML or install the product using the normal vBulletin Product Manager workflow.
3. Upload a compatible add-on package to its own `core/packages/<addon>/` directory.
4. Import/install that add-on's native vBulletin Product XML.

The site administrator does not need Composer, `composer install`, Artisan, npm, or a Laravel `.env` file. The distributable package contains its private Composer dependencies.

## Creating a VB Laravel add-on

An add-on can use the following minimal structure:

```text
core/packages/myaddon/
├── product.php
├── vb-addon.json
├── src/
└── xml/
```

Native vBulletin Product XML and product metadata remain the source of truth for native installation, routing, templates, phrases, permissions, settings, and Site Builder integration.

### `vb-addon.json`

```json
{
  "id": "Vendor/AddOn",
  "title": "Example Add-on",
  "version": "1.0.0",
  "runtime": "^0.1",
  "provider": "Vendor\\AddOn\\Provider"
}
```

`id` identifies the add-on, `title` is its display name, `version` is the add-on version, `runtime` declares the compatible VB Laravel Runtime line, and `provider` names the provider class loaded by `AddonLoader`.

## Minimal example

```php
$form = Form::with('fields')->findOrFail($id);

$user = vb()->user();

if (!vb()->can('some_permission')) {
    // deny
}
```

The example is illustrative. Native vBulletin still supplies the request lifecycle, authorization context, and presentation integration.

## Public Bridge API

The current public bridge methods are:

- `vb()->user()` — fetches the current vBulletin user information.
- `vb()->request()` — fetches native request information.
- `vb()->phrase(string $name)` — fetches a vBulletin phrase.
- `vb()->node(int $id)` — fetches a native node.
- `vb()->api(string $controller, ?string $method = null, array $arguments = [])` — accesses a native API or calls one method.
- `vb()->can(string $permission, string $group = 'genericpermissions')` — checks a native permission.
- `vb()->url(string $route, array $data = [], array $extra = [])` — builds a native vBulletin URL.

These are the implemented 0.1 bridge APIs. Planned or unimplemented APIs are not part of the public contract.

## Runtime API

- `Runtime::VERSION` — current Runtime version (`0.1.0`).
- `Runtime::state()` — returns `unloaded`, `booting`, `booted`, or `failed`.
- `Runtime::isBooted()` — reports whether Illuminate has booted successfully.
- `Runtime::boot(?string $vendorPath = null)` — explicitly loads the private Composer autoloader and returns the Illuminate Container.
- `Runtime::container()` — returns the existing Container; it throws until `Runtime::boot()` has been called.

Calling `Runtime::boot()` twice is safe and returns the same Container. Loading `bootstrap.php`, creating `vb()`, and using native bridge methods do not boot Illuminate.

## Laravel and vBulletin responsibilities

| Laravel / Illuminate | vBulletin |
| --- | --- |
| Data models | Authentication and session |
| Relationships | Usergroups and permissions |
| Query building | Nodes, topics, and posts |
| Application logic | Templates and phrases |
| Container and transactions | Routes and Site Builder |
| Eloquent | AdminCP and native lifecycle |

## XenForo developers

This is a migration mental map, not a XenForo clone:

| XenForo | VB Laravel |
| --- | --- |
| Entity | Eloquent Model |
| Finder | Eloquent Query / Scope |
| Repository | Service when necessary |
| Service | Service |
| Job | Laravel Job where supported |
| Permission | `vb()->can()` |
| Phrase | `vb()->phrase()` |
| URL | `vb()->url()` |
| Node/content | `vb()->node()` |

VB Laravel does not clone XenForo.

## Compatibility philosophy

Add-ons depend on a VB Laravel Runtime version, not directly on an internal Illuminate version. The Runtime controls shared Illuminate dependencies. vBulletin API and version differences should be absorbed by the Bridge where practical. Public APIs should follow `add → deprecate → remove` across major versions. The 0.x line is experimental.

## Dependency isolation

Private Composer vendor files reduce coupling and keep the Runtime separate from vBulletin's Composer vendor directory, but they do not guarantee complete PHP namespace isolation because both autoloaders run in one PHP process. No known conflict exists in the tested environment. Namespace prefixing or build isolation may be introduced later only if genuinely necessary.

## What is not included

- No Laravel Auth replacement.
- No Blade replacement.
- No Laravel Router replacement.
- No full Laravel HTTP Kernel.
- No marketplace.
- No add-on generator or DevKit yet.
- No promise that every Laravel package will work.

## Development principles

- Do not modify the vBulletin core.
- Keep the Runtime small.
- Reuse Laravel instead of recreating Laravel.
- Reuse vBulletin instead of recreating vBulletin.
- Keep `vb()` small and stable.
- Avoid unnecessary abstractions.

## Roadmap

Possible future work includes broader Bridge coverage, dependency isolation improvements if required, additional Illuminate capabilities, developer tooling, and compatibility testing across more vBulletin versions. No dates or feature guarantees are promised.

## Contributing, security, and license

See [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), and [LICENSE](LICENSE).

## Further documentation

See [COMPATIBILITY.md](COMPATIBILITY.md), [CHANGELOG.md](CHANGELOG.md), and [docs/dependency-collision-audit.md](docs/dependency-collision-audit.md).
