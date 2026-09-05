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

## 🚀 Key Features in 0.2.0

- ⚡ **Zero Overhead (Lazy Boot)**: Illuminate only boots when an add-on specifically requests it. Normal forum traffic is unaffected.
- 📦 **Dynamic PSR-4 Autoloading**: Add-on models, controllers, and services are automatically autoloaded. No manual `require_once` statements needed!
- 🗄️ **Centralized Eloquent**: Database connection is established automatically with `utf8mb4` encoding using native vBulletin settings (`vB::getConfig()`).
- 🛠️ **Developer Helpers**:
  - `collect($items)` — Laravel Collection magic for arrays and datasets.
  - `db('table')` — Fluent Query Builder shortcut.
  - `app()` — Access the dependency injection container.
  - `vb()->userId()` & `vb()->isLoggedIn()` — Quick authentication helpers.
  - `vb()->setting('name')` — Instant access to vBulletin options.
  - `vb()->session()` — Direct session access.

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

## 🛠️ Building an Add-on in 3 Steps

### 1. Structure

Create your package under `core/packages/myaddon/`:

```text
core/packages/myaddon/
├── product.php
├── vb-addon.json
├── src/
│   ├── Provider.php
│   └── Models/
│       └── Bookmark.php
└── xml/
    └── product_myaddon.xml
```

### 2. Manifest (`vb-addon.json`)

Declare your add-on identity and service provider:

```json
{
  "id": "Vendor/MyAddon",
  "title": "My Add-on",
  "version": "1.0.0",
  "runtime": "^0.2",
  "provider": "Vendor\\MyAddon\\Provider"
}
```

### 3. Service Provider (`src/Provider.php`)

```php
namespace Vendor\MyAddon;

use Illuminate\Support\ServiceProvider;

class Provider extends ServiceProvider
{
    public function register(): void
    {
        // Bind services to the container
    }

    public function boot(): void
    {
        // Eloquent is already booted and connected!
    }
}
```

### 4. Use Eloquent Models with Zero Boilerplate (`src/Models/Bookmark.php`)

```php
namespace Vendor\MyAddon\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    protected $table = 'myaddon_bookmarks';
    protected $primaryKey = 'bookmarkid';
    protected $guarded = [];
}
```

Because of dynamic PSR-4 autoloading, you can instantly use `Vendor\MyAddon\Models\Bookmark` in your controllers or vBulletin hooks without any manual `require_once`!

---

## 📖 Public Bridge API Reference

| Method | Description |
|---|---|
| `vb()->userId()` | Current logged-in user ID as integer (`0` if guest). |
| `vb()->isLoggedIn()` | Returns `true` if current visitor is authenticated. |
| `vb()->user()` | Fetches complete current userinfo array. |
| `vb()->can('perm', 'group')` | Checks native vBulletin user permissions. |
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

Run the included verification tests with PHP:

```bash
php tests/poc.php
php tests/package-layout.php
```

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
