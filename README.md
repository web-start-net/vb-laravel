# VB Laravel

**Develop modern vBulletin 6 add-ons with the elegance and power of Laravel.**

VB Laravel is a lightweight **Illuminate-powered runtime and bridge** that brings modern Laravel conventions — such as **Eloquent ORM, Collections, and Service Providers** — into vBulletin 6, **without modifying a single line of the vBulletin core**.

---

## 💡 The Concept: `Laravel + vb()`

Developing vBulletin add-ons traditionally required complex SQL arrays (`querydefs.php`), manual JOINs, and unfamiliar procedural APIs. 

**VB Laravel simplifies development into an intuitive mental model:**

- **Laravel (Illuminate)** powers your data layer: Eloquent models, relationships, collections, and transactions.
- **`vb()`** bridges directly to native vBulletin features: current user, permissions, phrases, nodes, and Site Builder.

```php
// Query custom data cleanly with Eloquent
$form = Form::with('fields')->where('enabled', true)->findOrFail($id);

// Access native vBulletin context seamlessly
$user = vb()->user();
$userId = vb()->userId();

if (!vb()->can('can_submit_forms')) {
    throw new Exception('Permission denied.');
}
```

---

## 🚀 Key Features in 0.3.0

- ⚡ **Zero Overhead (Lazy Boot)**: Illuminate only boots when an add-on specifically requests it. Normal forum traffic is unaffected.
- 📦 **Dynamic PSR-4 Autoloading**: Add-on models, controllers, and services are automatically autoloaded. No manual `require_once` statements needed!
- 🗄️ **Centralized Eloquent**: Database connection is established automatically with `utf8mb4` encoding using native vBulletin settings (`vB::getConfig()`).
- 🛠️ **Developer Helpers**:
  - `vb()` — Fast, zero-overhead global bridge to native vBulletin context.
  - `\VbLaravel\collect($items)` — Laravel Collection magic for arrays and datasets.
  - `\VbLaravel\db('table')` — Fluent Query Builder shortcut.
  - `\VbLaravel\app()` — Access the dependency injection container.
  - `\VbLaravel\Migrator` — Idempotent migrations powered by `Capsule::schema()`.
  - `VbLaravel\Models` — Read-only core models (`User`, `Node`, `Channel`) with Unix dateline (`$dateFormat = 'U'`).
  - `vbl` CLI (`vbl validate`, `vbl build`) — Static AST compilation of templates, phrases, settings, and hooks into certified product XML.

---

## 🏛️ Architecture

```text
vBulletin 6 Core
    │
    ├── Native vBulletin (Users, Permissions, Routing, Templates, Phrases)
    │
    └── VB Laravel Runtime (core/packages/vblaravel/)
          │
          ├── Illuminate Container & Collections
          ├── Eloquent ORM (Capsule Manager)
          ├── Dynamic AddonLoader (PSR-4)
          └── Native Bridge (vb())
```

---

## 📦 Installation

For forum administrators:

1. Upload the `package/core/packages/vblaravel/` directory to `core/packages/vblaravel/` on your forum server.
2. In **AdminCP**, go to **Products & Plugins** → **Manage Products** → **Add/Import Product**.
3. Import `packages/vblaravel/xml/product_vblaravel.xml` (Allow Overwrite: `Yes`).
4. Install any compatible add-on into `core/packages/<youraddon>/`.

> **Note**: No Composer, Node.js, or command-line setup is required on production servers. All runtime dependencies are self-contained.

---

## 🛠️ Building an Add-on with the `vbl` CLI

VB Laravel provides a complete CLI toolkit for scaffolding, developing, and packaging add-ons:

```bash
# 1. Scaffold a complete working add-on in seconds
php bin/vbl make:addon myaddon --title="My Add-on"

# 2. Add granular components
php bin/vbl make:model Review --table=myaddon_reviews
php bin/vbl make:migration create_reviews_table
php bin/vbl make:hook ContentListener --hook=hookFrontendContentAfterAdd
php bin/vbl make:api Review --controller=myaddon:review --action=submit --write
php bin/vbl make:template toolbar --hook

# 3. Validate conventions, schema, and AST attributes
php bin/vbl validate myaddon

# 4. Develop: build, copy, and auto-import into local test forum in one step
php bin/vbl dev myaddon --target=/path/to/vb/core

# 5. Package for distribution: generates upload-ready admin zip
php bin/vbl package myaddon
```

---

## 📚 Example Add-ons

Explore production-grade implementations in the `examples/` directory:
- [**`examples/bookmarks`**](examples/bookmarks): Eloquent models, bookmarks table migration, PHP hooks, and read/write APIs.
- [**`examples/wsforms`**](examples/wsforms): Multi-table migrations, form submission APIs, AdminCP settings, and permission gates.
- [**`examples/ratings`**](examples/ratings): 5-star thread rating system with interactive template hooks, write API, and settings.

---

## 📖 Public Bridge API Reference

| Method | Description |
|---|---|
| `vb()->userId()` | Current logged-in user ID as integer (`0` if guest). |
| `vb()->isLoggedIn()` | Returns `true` if current visitor is authenticated. |
| `vb()->user()` | Fetches complete current userinfo array. |
| `vb()->inGroup([6, 7])` | Checks if visitor belongs to any of the specified usergroup IDs. |
| `vb()->can('perm', 'group')` | Checks native vBulletin user permissions safely. |
| `vb()->setting('varname', $default)` | Reads an option directly from vBulletin datastore. |
| `vb()->session()` | Gets the current native session instance. |
| `vb()->phrase('name')` | Fetches a vBulletin phrase translation. |
| `vb()->node($id)` | Fetches a native vBulletin node (forum/thread/post). |
| `vb()->url('route', $data)` | Generates a native vBulletin SEO URL. |
| `vb()->api('controller', 'method', $args)` | Calls any native vBulletin 6 API method. |

---

## 🤝 Responsibility Matrix

| Feature | Handled By |
|---|---|
| **Data Models & Relationships** | Laravel Eloquent |
| **Collections & Transforming** | Laravel Collections (`collect()`) |
| **Dependency Injection** | Laravel Container (`app()`) |
| **User Authentication & Session** | vBulletin Core (`vb()->user()`) |
| **Permissions & Usergroups** | vBulletin Core (`vb()->can()`) |
| **Site Builder & Presentation** | vBulletin Templates & Widgets |
| **Forum Navigation & AdminCP** | vBulletin XML & AdminCP Scripts |

---

## 🧪 Testing

Run the automated test suite with PHP:

```bash
# Unit & AST Tests
php tests/compiler-ast-test.php
php tests/read-only-model-test.php
php tests/provider-lifecycle-test.php
php tests/cli-generators-test.php

# Live vBulletin End-to-End Suite (requires local vB install)
php tests/e2e-verify-suite.php
```

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
