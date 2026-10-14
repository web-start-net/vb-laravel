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

## 4. Migrations & Table Prefix Behavior (`VbLaravel\Migrator`)

Add-on database tables are created and managed via namespaced migrations using `Capsule::schema()` (no Laravel Facades).

- **Location**: `<addon>/database/migrations/YYYY_MM_DD_HHMMSS_name.php`
- **Tracking Table**: `<prefix>vbl_migrations`
- **Table Prefix Convention**:
  vBulletin installations configure a global table prefix in `core/includes/config.php` (e.g. `vb_` or `myforum_`). When VB Laravel boots the database, Capsule automatically adopts this prefix as its connection prefix.  
  **Rule**: In migrations and Eloquent models, define table names **without** hardcoding the global DB prefix (e.g. `ws_bookmarks` or `wsratings_ratings`). Eloquent will automatically prefix queries with the global forum prefix if configured.
- **Migration Format**:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration {
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('ws_custom_table')) {
            Capsule::schema()->create('ws_custom_table', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('userid')->index();
                $table->string('title', 150);
                $table->unsignedInteger('created_at')->default(0);
                $table->unsignedInteger('updated_at')->default(0);
            });
        }
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

## 5. Attributes, Hook Architecture & Frontend Bridge

### 5.1 PHP Hooks (`#[Hook]`)
- Applied to methods in add-on listener classes.
- Target verified vBulletin 6 hooks documented in `data/vb6-hooks.json` (e.g. `hookFrontendContentAfterAdd`, `hookFrontendPreheader`, `hookUserAfterSave`).
- Handlers **must** accept parameters by reference: `public function onEvent(array &$params): void`.

### 5.2 API Controllers, Permissions & Error Convention (`#[Api]`, `#[Can]`)
- `#[Api('package:controller', 'action', write: false, guest: false, node: null)]`: Declares an exposed API action.
  - `write`: Set to `true` for mutating actions (checks CSRF token and rejects guests by default).
  - `guest`: Set to `true` if an action (especially a write action) is explicitly permitted for unauthenticated guests (`userid === 0`). Defaults to `false` for write endpoints.
  - `node`: Name of a parameter representing a vBulletin node ID (e.g. `node: 'nodeid'`). The generated wrapper automatically queries `\vB_Api::instanceInternal('node')->getNode((int) $nodeid)`. If the node does not exist or the current viewer has no permission to view it, `\vB_Exception_Api('invalid_node')` is thrown before invoking the controller method.
- `#[Can('permission', 'group')]`: Allowed **only** on `#[Api]` endpoints.
- **Error Convention**:
  - Always throw `\vB_Exception_Api('phrase_key')` on error (e.g. `throw new \vB_Exception_Api('no_permission');` or `throw new \vB_Exception_Api('cannot_thank_own_post');`).
  - vBulletin's API gateway catches `\vB_Exception_Api` and translates it into standard JSON: `{"errors":[["phrase_key"]],"userid":...}`. Do not return custom `['error' => '...']` arrays.
- **CSRF & HTTP Method Requirement**:
  - In vBulletin 6, **all `/ajax/api/...` endpoints require HTTP POST**. `vB5_Frontend_ApplicationLight` enforces `requirePost` on the `ajax/api` route; calling `/ajax/api` with HTTP `GET` always throws `vB5_Exception('Incorrect HTTP Method. Please use a POST request.')`.
  - vBulletin 6 automatically validates `securitytoken` for all `POST /ajax/api` calls at the application gateway (`vB5_Frontend_ApplicationLight::execute()` -> `vB_Api_State::checkCSRF()`). Generated API wrappers rely on this native vB6 check.
  - Endpoints declared with `write: true` reject unauthenticated users (`userid === 0`) with `no_permission` by default (`guest: false`). To allow unauthenticated guest writes, explicitly declare `guest: true`.
  - **Important**: Never call `write: true` API methods from vBulletin templates (`{vb:data}`) — CSRF validation is only enforced on `POST /ajax/api`. Template data calls are strictly read-only.

### 5.3 Calling APIs from JavaScript
To call an add-on API endpoint from the frontend browser:
- **Endpoint URL**: `/ajax/api/<package>.<controller>/<action>` (e.g. `/ajax/api/wsratings.rating/rate`).
- **HTTP Method**: Must **always** be `POST` (even for read operations).
- **CSRF Token & Base URL Resolution**:
  Always inspect `#pagedata` first, followed by global runtime variables:
- **Robust Copy-Paste JavaScript Pattern**:
```javascript
function getToken() {
    var pd = document.getElementById('pagedata');
    if (pd && pd.getAttribute('data-securitytoken')) {
        return pd.getAttribute('data-securitytoken');
    }
    if (typeof vBulletin !== 'undefined' && vBulletin.securitytoken) {
        return vBulletin.securitytoken;
    }
    if (typeof SECURITYTOKEN !== 'undefined') {
        return SECURITYTOKEN;
    }
    var input = document.querySelector('input[name="securitytoken"]');
    return input ? input.value : '';
}

function getBaseUrl() {
    var pd = document.getElementById('pagedata');
    if (pd && pd.getAttribute('data-baseurl')) {
        return pd.getAttribute('data-baseurl').replace(/\/+$/, '') + '/';
    }
    if (typeof pageData !== 'undefined' && pageData.baseurl) {
        return pageData.baseurl.replace(/\/+$/, '') + '/';
    }
    if (typeof vBulletin !== 'undefined') {
        if (typeof vBulletin.getAjaxBaseurl === 'function') {
            return vBulletin.getAjaxBaseurl().replace(/\/+$/, '') + '/';
        }
        if (vBulletin.baseurl) {
            return vBulletin.baseurl.replace(/\/+$/, '') + '/';
        }
    }
    return '/';
}

function callAddonApi(endpoint, data) {
    var body = new URLSearchParams(data || {});
    var token = getToken();
    if (token) {
        body.append('securitytoken', token);
    }

    return fetch(getBaseUrl() + 'ajax/api/' + endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
    }).then(function(response) {
        return response.json();
    });
}

// Example usage:
callAddonApi('wsratings.rating/rate', {
    nodeid: 123,
    rating: 5
}).then(function(result) {
    if (result.errors) {
        console.error('API Error:', result.errors[0][0]);
    } else {
        console.log('Success:', result);
    }
});
```

### 5.4 Common Template Hooks & Variables
Template hooks are rendered by vBulletin templates via `{vb:hook 'hookname'}`.
Create template hooks under `<addon>/templates/hooks/<hookname>.html`.
The `vbl validate` tool automatically checks `{vb:raw ...}`, `{vb:var ...}`, `<vb:if condition="...">`, and `<vb:each>` tags against the verified in-scope variables defined in `data/vb6-hooks.json`:

| Hook Name | Common Location | In Scope Variables |
|---|---|---|
| `header_head` | Inside `<head>` on all forum pages | `page`, `baseurl` |
| `header_after_body_begin` | Immediately after `<body>` opens | `page`, `baseurl` |
| `header_before_content` | Main content wrapper start | `page`, `baseurl` |
| `conversation_display_controls` | Thread toolbar beside Follow/Share button | `conversation`, `nodeid`, `page` |
| `conversation_before_display_controls` | Before the thread toolbar button group | `conversation`, `nodeid`, `page` |
| `conversation_above_entry` | Above the thread reply box | `conversation`, `nodeid`, `page` |
| `conversation_below_entry` | Below the thread reply box | `conversation`, `nodeid`, `page` |
| `conversation_post_above` | Directly above an individual post/reply | `conversation` |
| `conversation_post_below` | Directly below an individual post/reply | `conversation` |
| `conversation_footerright` | Inside post action controls list (Reply/Quote/etc.) | `conversation` |
| `conversation_footerleft` | Left side of post footer | `conversation` |
| `conversation_userstats` | User stats block in postbit | `conversation`, `userInfo` |
| `forumitem_below_description` | Below channel description in forum list | `forum`, `avatar` |
| `forumlist_before_categories` | Top of the forum channel list | `channel`, `forum` |
| `footer_start` | Beginning of the page footer | `page`, `baseurl` |
| `footer_end` | End of the page footer | `page`, `baseurl` |
| `footer_before_body_end` | Right before `</body>` closes | `page`, `baseurl` |

---

## 6. Settings & Localization Formats

### 6.1 AdminCP Options (`config/settings.php`)
Settings declared in `config/settings.php` are compiled into native vBulletin option groups:
```php
<?php

declare(strict_types=1);

return [
    'group_title' => 'My Add-on Settings',
    'displayorder' => 1000,
    'settings' => [
        'myaddon_enabled' => [
            'title' => 'Enable My Add-on',
            'description' => 'Enable or disable add-on functionality.',
            'type' => 'boolean', // boolean, integer, or free (string)
            'default' => 1,
            'displayorder' => 10,
        ],
    ],
];
```

### 6.2 Phrases & Translation (`lang/en.php`)
Phrase keys must follow vBulletin naming conventions:
- `settinggroup_<id>`: Group title in AdminCP options.
- `setting_<varname>_title`: Title of the individual setting.
- `setting_<varname>_desc`: Description under the setting input.
- `<id>_<key>`: Custom frontend phrases, accessible via `{vb:phrase myaddon_welcome}` or `vb()->phrase('myaddon_welcome')`.

```php
<?php

declare(strict_types=1);

return [
    'settinggroup_myaddon' => 'My Add-on Settings',
    'setting_myaddon_enabled_title' => 'Enable My Add-on',
    'setting_myaddon_enabled_desc' => 'Enable or disable add-on functionality.',
    'myaddon_welcome' => 'Welcome to My Add-on',
];
```

---

## 7. Developer Tooling (`vbl` CLI)

The CLI tool and its AST parser are strictly dev-only (`require-dev`) and not included in production package distributions.

```bash
# Scaffold a complete working add-on (passes validate + build immediately)
php bin/vbl make:addon <id> --title="My Add-on" [--dir=path] [--folder=name]

# Granular code generators
php bin/vbl make:model <Name> [--table=...] [--addon=path]
php bin/vbl make:migration <Name> [--addon=path]
php bin/vbl make:hook <Name> [--hook=hookName] [--order=10] [--addon=path]
php bin/vbl make:api <Name> [--controller=ctrl] [--action=act] [--write] [--addon=path]
php bin/vbl make:template <Name> [--hook] [--addon=path]

# Validate an add-on against schema, rules, and hook naming
php bin/vbl validate path/to/addon

# Compile templates, phrases, settings, migrations, and hooks into product_<id>.xml
php bin/vbl build path/to/addon

# One-command build + copy + re-import into local vBulletin test installation
php bin/vbl dev path/to/addon [--target=/path/to/vb/core]

# Package add-on into an upload-ready zip for forum administrators
php bin/vbl package path/to/addon [--output=dist/]

# Synchronize single source of truth (src/ -> package/) and bump version
php bin/vbl release [version]
```

---

## 8. Add-on Manifest Schema (`vb-addon.json`)

All add-ons must provide a `vb-addon.json` conforming to `schemas/vb-addon.schema.json`:
- `id`: Lowercase alphanumeric string matching `^[a-z][a-z0-9]{2,24}$`.
- `name` or `title`: Display title of the add-on.
- `version`: Semver version string (e.g. `1.0.0`).
- `runtime`: Caret constraint matching `^\^(\d+)\.(\d+)(?:\.(\d+))?$` (e.g. `^0.3`, `^1.0`).
- `provider`: Fully-qualified class name of the add-on's service provider.

---

## 9. Reference Implementations

The repository contains three complete, production-grade example add-ons demonstrating standard patterns:

1. **`examples/bookmarks`** (`wsbookmarks`):
   - Custom bookmarking table and migration.
   - Eloquent model `Bookmark` with `$dateFormat = 'U'`.
   - Read and write API endpoints (`wsbookmarks:bookmark/get`, `wsbookmarks:bookmark/toggle`) with `\vB_Exception_Api` error convention.
   - PHP hook `hookFrontendPreheader` for lazy datastore check.
   - Template hook in `header_head.html`.

2. **`examples/wsforms`** (`wsforms`):
   - Advanced multi-table schema (forms, form fields, form submissions, submission values).
   - Dynamic form validation and submissions with `\vB_Exception_Api`.
   - AdminCP options and phrases (`config/settings.php`, `lang/en.php`).
   - Complex permissions using `#[Can]`.

3. **`examples/ratings`** (`wsratings`):
   - 5-star thread rating system built and verified by AI.
   - Table `wsratings_ratings` tracking `nodeid`, `userid`, `rating` (1–5), `dateline`.
   - API endpoints: `get` (stats & user rating), `rate` (authenticated `write: true`, `node: 'nodeid'`).
   - Interactive star rating JavaScript with CSRF securitytoken handling.
   - Template hooks: `templates/hooks/conversation_post_above.html` and `templates/hooks/header_head.html`.
   - AdminCP setting `wsratings_enabled` and frontend phrases.

4. **`examples/thanks`** (`wsthanks`):
   - Per-post "Thank" button rendered inside the post control bar.
   - Table `wsthanks_thanks` tracking `nodeid`, `userid`, `created_at` with unique compound index.
   - API endpoints: `get` (per-node thank count and viewer thank state), `toggle` (`write: true`, `node: 'nodeid'`).
   - Self-thanking prevention throwing `\vB_Exception_Api('cannot_thank_own_post')`.
   - Template hook in `conversation_footerright.html` rendered seamlessly per post in thread view.

