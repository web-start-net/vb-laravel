# VB Laravel — Agent & Developer Specification

This document defines the architecture, public API surface, conventions, and constraints for **VB Laravel** (`vb-laravel/runtime`) and add-ons built on top of it.

---

## 1. Architectural Principles

### 1.1 Sacred Lazy Booting
- **Zero Runtime Overhead**: Illuminate components and vendor autoloaders are never loaded during ordinary forum requests unless an add-on handler, hook, or API explicitly invokes `\VbLaravel\Runtime::boot()`, `\VbLaravel\app()`, `\VbLaravel\db()`, or an Eloquent model.
- **No Runtime Filesystem Scans**: No `glob()`, recursive scans, or runtime Reflection. All hook registrations, settings, templates, and phrases are compiled at build time by `vbl build` into native vBulletin product XML and package glue classes.

### 1.2 Dual Connection Model & Transaction Isolation
- **Handle Separation**: Native vBulletin 6 connects to MySQL via `\mysqli` (`vB_Db_Mysql_Connection::$connection_master`). Eloquent strictly requires a PDO connection and runs through a separate, lazily established PDO connection managed by `Illuminate\Database\Capsule\Manager`.
- **Transaction Warning**:
  > **Important**: Transactions executed on Eloquent (e.g. `\VbLaravel\db()->beginTransaction()` or `Capsule::connection()->transaction(...)`) execute solely on the PDO connection and **do not encompass** native vBulletin queries executed via `\vB::getDbAssertor()`.  
  > A rollback in Eloquent will not rollback vBulletin assertor writes, and vice-versa. Application-level coordination is required when writing to both layers simultaneously.

---

## 2. Public API Surface

### 2.1 The Global Bridge: `vb()`
Only `vb()` is exposed globally in the root namespace. It acts as an ultra-fast, zero-overhead bridge to vBulletin 6 internals.

```php
// Current user (memoized per request; returns empty array on errors or unauthenticated guests)
$user = vb()->user();
$userId = vb()->userId();
$isLoggedIn = vb()->isLoggedIn();

// Usergroup verification (checks both primary usergroupid and comma-separated membergroupids)
if (vb()->inGroup([6, 7])) { // e.g. Administrators or Moderators
    // ...
}

// Safe permission check (gracefully guarded when user context is uninitialized or in CLI)
if (vb()->can('canview', 'genericpermissions')) {
    // ...
}

// Access datastore options (uses strictly \vB::getDatastore())
$enabled = vb()->setting('myaddon_enabled', true);

// Native API dispatch
$node = vb()->api('node', 'getNode', [$nodeId]);
```

### 2.2 Namespaced Helpers (`\VbLaravel`)
Developer helpers are strictly isolated in the `\VbLaravel` namespace to prevent pollution:

```php
use function VbLaravel\app;
use function VbLaravel\collect;
use function VbLaravel\db;

// Fluent query builder via Eloquent Capsule
$rows = db('ws_bookmarks')->where('userid', vb()->userId())->get();

// Illuminate Collections
$doubled = collect([1, 2, 3])->map(fn($n) => $n * 2);

// Dependency Injection Container
$service = app(MyService::class);
```

---

## 3. Core Read-Only Models (`VbLaravel\Models`)

VB Laravel provides read-only Eloquent models mapping to native vBulletin core tables:
- `VbLaravel\Models\User` (`user`, primaryKey `userid`)
- `VbLaravel\Models\Node` (`node`, primaryKey `nodeid`)
- `VbLaravel\Models\Channel` (`channel`, primaryKey `nodeid`)

### 3.1 Dateline Convention
vBulletin timestamps are Unix epoch integers (`dateline`). All models extend `ReadOnlyModel` with:
```php
protected $dateFormat = 'U';
```

### 3.2 Write Immutability
All core models inherit from `ReadOnlyModel`. Calling `save()`, `update()`, `create()`, or `delete()` on these models throws `\BadMethodCallException`.
> **Rule**: Writes to core vBulletin tables must **always** go through native vBulletin APIs (`vb()->api(...)`) to preserve node closure hierarchy, caches, moderation workflows, permissions, and search indexing.

---

## 4. Migrations (`VbLaravel\Migrator`)

Add-on database tables are created and managed via namespaced migrations using `Capsule::schema()` (no Laravel Facades).

- **Location**: `<addon>/database/migrations/YYYY_MM_DD_HHMMSS_name.php`
- **Tracking Table**: `<prefix>vbl_migrations`
- **Format**:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration {
    public function up(): void
    {
        Capsule::schema()->create('ws_custom_table', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('userid')->index();
            $table->string('title', 150);
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('ws_custom_table');
    }
};
```
- **Execution**:
  `Migrator::up(DIR . '/packages/<id>')` is idempotent and runs during product installation and in each version upgrade code block.  
  `Migrator::down(DIR . '/packages/<id>')` rolls back migrations during product uninstallation.

---

## 5. Attributes & Hook Architecture

### 5.1 PHP Hooks (`#[Hook]`)
- Applied to methods in add-on listener classes.
- Target verified vBulletin 6 hooks (e.g. `hookFrontendContentAfterAdd`, `hookFrontendContentBeforeAdd`, `hookUserAfterSave`).
- Handlers **must** accept parameters by reference: `public function onEvent(array &$params): void`.

### 5.2 API & Permissions (`#[Api]`, `#[Can]`)
- `#[Api('package:controller', 'action')]`: Declares an exposed API action.
- `#[Can('permission', 'group')]`: Allowed **only** on `#[Api]` endpoints.
- **CSRF Protection**: Write actions (`save*`, `submit*`, `create*`, `delete*`) automatically require and validate `securitytoken`.

### 5.3 Template Hooks vs PHP Hooks
- Template hooks rendered via `{vb:hook 'hookname'}` (such as `header_head`) must **not** use `#[Hook]`.
- Place template hook HTML files in `<addon>/templates/hooks/<hookname>.html`.
- `vbl build` automatically routes these into `<hooks>` and `<templates>` in the generated product XML.

---

## 6. Developer Tooling (`vbl` CLI)

The CLI tool and its AST parser are strictly dev-only (`require-dev`) and not included in production package distributions.

```bash
# Validate an add-on against schema, rules, and hook naming
php bin/vbl validate path/to/addon

# Compile templates, phrases, settings, migrations, and hooks into product_<id>.xml
php bin/vbl build path/to/addon
```

---

## 7. Add-on Manifest Schema (`vb-addon.json`)

All add-ons must provide a `vb-addon.json` conforming to `schemas/vb-addon.schema.json`:
- `id`: Lowercase alphanumeric string matching `^[a-z][a-z0-9]{2,24}$`.
- `name` or `title`: Display title of the add-on.
- `version`: Semver version string (e.g. `1.0.0`).
- `runtime`: Caret constraint matching `^\^(\d+)\.(\d+)(?:\.(\d+))?$` (e.g. `^0.2`, `^0.10`, `^1.0`).
- `provider`: Fully-qualified class name of the add-on's service provider.
