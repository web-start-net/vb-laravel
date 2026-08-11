# VB Laravel

VB Laravel is a lightweight Illuminate-powered runtime and compatibility bridge for building modern vBulletin 6 add-ons without modifying the vBulletin core.

The public mental model is:

```text
Standard Laravel / Illuminate
+
vb()
```

Laravel handles application logic. vBulletin handles vBulletin concerns. `vb()` is the small bridge between them.

## Central idea

```php
$resource = Resource::findOrFail($id);

$user = vb()->user();

return vb()->view('resource_show', [
    'resource' => $resource,
]);
```

The example illustrates the intended style; only APIs listed below are currently implemented. VB Laravel does not currently provide `Resource`, `view()`, or an application router.

## Runtime

Loading the package bootstrap registers `vb()` and leaves Illuminate unloaded:

```php
require_once DIR . '/packages/vblaravel/bootstrap.php';

$user = vb()->user();
VbLaravel\Runtime::boot();
$container = VbLaravel\Runtime::container();
```

`Runtime::container()` never boots the runtime implicitly. Call `Runtime::boot()` first. A second boot is safe and returns the same container.

The optional argument to `Runtime::boot()` is a development package root containing `vendor/autoload.php`; normal installs use the package's own private vendor tree automatically.

## Stable 0.1 API

Runtime:

- `Runtime::VERSION`
- `Runtime::state()`
- `Runtime::isBooted()`
- `Runtime::boot(?string $vendorPath = null)`
- `Runtime::container()`

Bridge:

- `vb()->user()`
- `vb()->request()`
- `vb()->phrase(string $name)`
- `vb()->node(int $id)`
- `vb()->api(string $controller, ?string $method = null, array $arguments = [])`
- `vb()->can(string $permission, string $group = 'genericpermissions')`
- `vb()->url(string $route, array $data = [], array $extra = [])`

Each bridge method delegates to a native vBulletin API. These methods form the 0.1 public bridge surface; future changes should add, deprecate, and remove only at a major release where practical.

## Laravel-powered add-ons

An add-on contains a deliberately small runtime manifest:

```text
core/packages/vendoraddon/
├── product.php
├── vb-addon.json
├── src/
└── xml/
```

```json
{
    "id": "Vendor/AddOn",
    "title": "Example Add-on",
    "version": "1.0.0",
    "runtime": "^0.1",
    "provider": "Vendor\\AddOn\\Provider"
}
```

The manifest is for VB Laravel's loader. Native vBulletin Product metadata remains authoritative for vBulletin installation and product compatibility. Add-ons depend on the VB Laravel Runtime, not on its internal Illuminate version. The runtime owns the shared private Illuminate dependency set; add-ons must not bundle another Laravel framework copy.

## Installation

For a site administrator:

```text
Upload the vblaravel package
→ Import/install its vBulletin Product
→ Upload a Laravel-powered add-on
→ Import/install the add-on's vBulletin Product
→ Done
```

The normal installation does not require `composer install`, Artisan, npm, `.env`, or Laravel configuration. Add-on development uses the project's Composer setup and the real vBulletin development/test installation.

## XenForo developer mapping

This is a familiarity aid, not an attempt to clone XenForo:

| XenForo | VB Laravel |
| --- | --- |
| `addon.json` | `vb-addon.json` |
| Entity | Eloquent Model |
| Finder | Eloquent Query / Scope |
| Repository | Service when necessary |
| Service | Service |
| Job | Laravel Job |
| Controller | Controller |
| Permission | `vb()->can()` |
| Phrase | `vb()->phrase()` |
| Template | `vb()->view()` when implemented by the bridge |
| URL | `vb()->url()` |

Use this for add-ons you own or have permission to port. VB Laravel is not a XenForo compatibility layer.

## Current scope

0.1.0 freezes the proven runtime, bridge, private Composer vendor tree, manifest loader, and the minimal `vblaraveltest` integration fixture. It does not provide Blade, Laravel Router, HTTP or Console Kernels, Auth, queues, cache infrastructure, marketplace discovery, an installer, or a full migration framework.

See [COMPATIBILITY.md](COMPATIBILITY.md), [docs/dependency-collision-audit.md](docs/dependency-collision-audit.md), and [CHANGELOG.md](CHANGELOG.md).
