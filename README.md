# Smart Inventory Management API

A small but deliberately solid Laravel backend for Products, Categories, Warehouses, Stock and
Stock Movements. Built for the PHP/Laravel practical assessment.

- **Stack:** PHP 8.2, Laravel 11, MySQL 8 / MariaDB, Redis (cache)
- **Database:** `logieagle_db`
- **Cache driver:** Redis (`predis` client so no PHP extension is required; switch to `phpredis`
  by setting `REDIS_CLIENT=phpredis` if the extension is installed)
- **No extra packages for core logic:** only `predis/predis` is added as the Redis client.

---

## 1. Setup instructions

This section is written for someone starting from a clean machine. Run every command from the
**project root** (the folder that contains `artisan`, `composer.json` and this README), unless a
step says otherwise. The examples use Windows PowerShell with XAMPP because that is the local
development environment used for this project; the differences for macOS/Linux are shown where
they matter.

### 1.1 What must be installed first

| Tool | Required version | Why it is needed |
|------|------------------|------------------|
| Git | any recent version | to clone the repository |
| PHP | 8.1 or newer (developed on 8.2) | runs Laravel |
| Composer | 2.x | installs Laravel and `predis/predis` |
| MySQL 8 or MariaDB 10.x | any current release | stores the application data (XAMPP's MariaDB works) |
| Redis server | 5 or newer | cache store for the tree, listing, summary and low-stock endpoints |

You do **not** need the `phpredis` PHP extension. The project uses the `predis/predis` PHP client,
so a plain Redis server is enough. If you later install `phpredis`, change `REDIS_CLIENT` to
`phpredis` in `.env`.

Optional version checks:

```powershell
php -v
composer --version
mysql --version
redis-cli --version
```

### 1.2 Clone the repository

Open PowerShell and choose a folder where you keep your websites. With XAMPP the usual folder is
`C:\xampp\htdocs`:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/riddhigajjar1210/smart-inventory-management-api.git
cd smart-inventory-management-api
```

On macOS/Linux the same commands work from any folder:

```bash
git clone https://github.com/riddhigajjar1210/smart-inventory-management-api.git
cd smart-inventory-management-api
```

If you already cloned the repository before, you do not need to clone it again. Just open the
folder and get the latest code:

```powershell
cd C:\xampp\htdocs\smart-inventory-management-api
git pull origin main
```

You should now be inside a folder containing `artisan`, `app/`, `database/`, `routes/`,
`README.md`, `.env.example` and `composer.json`. That is the project root. Every remaining
`php artisan` command must be run here.

### 1.3 Install PHP dependencies

```powershell
composer install
```

This reads `composer.lock`, downloads Laravel 11 and the single extra package (`predis/predis`),
and creates the `vendor/` directory. `vendor/` is intentionally not committed to GitHub.

If Composer reports a missing PHP extension, enable the extension in your `php.ini` (XAMPP:
`C:\xampp\php\php.ini`) and restart Apache/PHP. The important ones for Laravel are `pdo_mysql`,
`mbstring`, `openssl`, `tokenizer`, `ctype`, `json`, `fileinfo` and `curl`.

### 1.4 Create the two MySQL databases

The application uses `logieagle_db`. The test suite uses a separate database called
`logieagle_db_test` so tests never destroy your working data. Both must exist before migrations.

**Option A — phpMyAdmin (easiest with XAMPP)**

1. Open `http://localhost/phpmyadmin` in your browser.
2. Log in with user `root` and an empty password (XAMPP default).
3. Click **New**, create `logieagle_db`, choose collation `utf8mb4_unicode_ci`, click **Create**.
4. Repeat and create `logieagle_db_test` with the same collation.

**Option B — MySQL command line (Windows/XAMPP)**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS logieagle_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS logieagle_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Option C — MySQL command line (macOS/Linux)**

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS logieagle_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS logieagle_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If your `root` user has a password, add `-p` on Windows too, or supply `-u your_user -p` with a
user that is allowed to create databases. Verify that both databases exist:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SHOW DATABASES LIKE 'logieagle%';"
```

### 1.5 Create and configure the `.env` file

The repository contains `.env.example` but deliberately **never** contains `.env`, because `.env`
holds machine-specific settings and secrets. Copy the example to a real environment file:

```powershell
Copy-Item .env.example .env -Force
php artisan key:generate
```

macOS/Linux equivalent:

```bash
cp .env.example .env
php artisan key:generate
```

`php artisan key:generate` writes a random `APP_KEY` into `.env`. Without it Laravel cannot
encrypt sessions or cookies and the app will throw a "No application encryption key has been
specified" error.

Open `.env` in a text editor and confirm these values (they are already the defaults in
`.env.example`):

```dotenv
APP_NAME="Smart Inventory API"
APP_ENV=local
APP_KEY=base64:...generated_above...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=logieagle_db
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

Change `DB_USERNAME` and `DB_PASSWORD` if your MySQL user is different. With a default XAMPP
install the username is `root` and the password is empty, so leave `DB_PASSWORD=` exactly as
shown. Keep `CACHE_STORE=redis` — the caching tests and the `cache:warm` command expect Redis.

> `.env` is listed in `.gitignore`. Never commit it and never paste its contents into a ticket,
> screenshot or chat. Share only `.env.example`, which contains placeholders.

### 1.6 Start Redis

Redis must be running before you warm or use the cache. Any Redis 5+ server is fine.

**Windows:** install a Redis service such as Memurai/Redis for Windows, or use a portable
`redis-server.exe`. Start it and leave the window open:

```powershell
C:\Users\<your-user>\AppData\Local\Temp\redis-portable\redis-server.exe --port 6379 --appendonly no
```

**macOS (Homebrew):**

```bash
brew install redis
brew services start redis
```

**Linux (Debian/Ubuntu):**

```bash
sudo apt update
sudo apt install redis-server
sudo systemctl start redis-server
```

Verify that Redis answers:

```powershell
redis-cli ping
```

You should see:

```text
PONG
```

If you see `Could not connect to Redis`, start the server and check that port `6379` is not
blocked. Because `REDIS_CLIENT=predis`, no PHP extension installation is required.

### 1.7 Migrate the database and load the seed data

Make sure MySQL is running, then run:

```powershell
php artisan migrate:fresh --seed --force
```

`migrate:fresh` drops every table and runs all migrations again, so use it only on a local or test
database. It creates these five tables, each with its indexes and comments:

- `categories`
- `products`
- `warehouses`
- `stock`
- `stock_movements`

The seeders then load realistic data:

- 12 categories, including nested children, one inactive parent with an active child, and at
  least one category with no products
- 50 products
- 3 warehouses
- roughly 150 stock rows, with products spread across multiple warehouses and at least one
  zero-stock product
- 200 stock movement records

You can confirm the counts in MySQL:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root logieagle_db -e "SELECT (SELECT COUNT(*) FROM categories) AS categories, (SELECT COUNT(*) FROM products) AS products, (SELECT COUNT(*) FROM warehouses) AS warehouses, (SELECT COUNT(*) FROM stock) AS stock_rows, (SELECT COUNT(*) FROM stock_movements) AS movements;"
```

### 1.8 Warm the caches (recommended)

With Redis running, prime the two hot keys:

```powershell
php artisan cache:warm
```

This fills `categories:tree:v1` and `inventory:summary:v1` so the first real request is fast and
so you can immediately confirm that Redis integration works. All keys, TTLs and invalidation
rules are documented in section 5.

### 1.9 Start the API server

Keep this command running in its own terminal:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

You should see:

```text
Starting Laravel development server: http://127.0.0.1:8000
```

The API base URL is:

```text
http://127.0.0.1:8000/api
```

Open these URLs in a browser to confirm the endpoints respond with JSON:

```text
http://127.0.0.1:8000/api/categories/tree
http://127.0.0.1:8000/api/products
http://127.0.0.1:8000/api/inventory/summary
http://127.0.0.1:8000/api/inventory/low-stock?threshold=50
http://127.0.0.1:8000/api/products/1/movements
```

### 1.10 Run the test suite

Open a **second** terminal (leave `php artisan serve` running) and run:

```powershell
php artisan test
```

Expected result:

```text
Tests:    16 passed (78 assertions)
```

The suite uses `logieagle_db_test` and the array cache driver configured in `phpunit.xml`, so it
does not touch `logieagle_db` or Redis. If you see a database error, create `logieagle_db_test`
as described in section 1.4. To run one file only:

```powershell
php artisan test --filter=StockAdjustmentTest
```

### 1.11 Quick write test (POST /api/stock/adjust)

From PowerShell, send a small `stock_in` adjustment:

```powershell
$body = @{
    product_id    = 1
    warehouse_id  = 2
    movement_type = 'stock_in'
    quantity      = 10
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/stock/adjust' -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 6
```

A successful response contains the updated stock row with `available_quantity`. The same request
also inserts an immutable row into `stock_movements` and bumps the relevant cache versions.

### 1.12 One-click helper on Windows

For a quick local demo, double-click `TEST_PRACTICAL.bat` in the project root. It starts Redis if
needed, runs `migrate:fresh --seed --force`, warms the caches, runs the tests, starts
`php artisan serve`, and opens the category tree in your browser. `HOW_TO_TEST.md` contains the
same steps in plain language.

### 1.13 Common setup problems

| Symptom | Cause | Fix |
|---------|-------|-----|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is not running or wrong `DB_HOST`/`DB_PORT` | Start XAMPP MySQL; confirm `DB_HOST=127.0.0.1`, `DB_PORT=3306` |
| `Unknown database 'logieagle_db'` | Database was not created | Repeat section 1.4 |
| `No application encryption key has been specified` | `.env` missing or `APP_KEY` empty | Copy `.env.example` to `.env`, then run `php artisan key:generate` |
| `Connection refused` from Redis / cache commands | Redis server is not running | Start Redis; confirm `redis-cli ping` returns `PONG` |
| `Class "Predis\Client" not found` | Dependencies not installed | Run `composer install` from the project root |
| Browser shows 404 on `/api/...` | Server started from the wrong folder or prefix omitted | Start `php artisan serve` in the project root and include `/api` |
| Tests fail with `logieagle_db_test` missing | Test database was not created | Repeat section 1.4 for the test database |

---

## 2. API reference

All responses are JSON. Errors use:

```json
{
  "message": "Human readable message",
  "errors": { "field": ["Detail"] }
}
```

Validation errors and business-rule violations both return **422**; unexpected errors return
**500** with no stack trace when `APP_DEBUG=false`.

| Method | Endpoint | Parameters | Returns |
|--------|----------|------------|---------|
| `GET` | `/api/categories/tree` | — | Nested category tree. Inactive categories removed; active children promoted to nearest active ancestor. Cached. |
| `GET` | `/api/products` | `category`, `warehouse`, `available`, `min_price`, `max_price`, `search`, `sort` (`name`\|`price`\|`available`), `direction` (`asc`\|`desc`), `per_page` (1–100, default 15) | Paginated products with category, stock rows, total quantity, total reserved and available total. All filters combine. |
| `GET` | `/api/products/{id}/movements` | `date_from` (`Y-m-d`), `date_to` (`Y-m-d`), `per_page` (default 20) | Paginated movement list plus a `summary` block with `total_units_in`, `total_units_out` and `net_movement` for the filtered period. |
| `POST` | `/api/stock/adjust` | `product_id`, `warehouse_id`, `movement_type`, `quantity`, `reference_type?`, `reference_id?`, `note?` | Updated stock row including `available_quantity`. Writes an immutable movement log row in the same transaction. |
| `GET` | `/api/inventory/summary` | — | Per product: total quantity, total reserved, available quantity, highest-stock warehouse and `is_zero_stock` flag. Cached; invalidated after every adjustment. |
| `GET` | `/api/inventory/low-stock` | `threshold` (default 10), `per_page` (default 20) | Product name, SKU, warehouse name, quantity, reserved and available, ordered by available ascending. Cached. |

### Movement types

`stock_in`, `stock_out`, `reservation`, `reservation_release` (PHP backed enum `MovementType`).

`stock_out` and `reservation` are rejected with 422 when available stock would fall below zero.
`reservation_release` is rejected when it exceeds the currently reserved quantity.

---

## 3. Concurrency and write correctness

`POST /api/stock/adjust` does all of the following inside one `DB::transaction`:

1. `SELECT ... FOR UPDATE` on the `stock` row for `(product_id, warehouse_id)`. The composite
   **unique** index on those two columns guarantees there is at most one row to lock, so two
   simultaneous requests for the same pair serialise at the database level — not in PHP.
2. If no row exists, it is inserted; a duplicate-key race on first creation is caught and the
   winner's row is re-read under lock.
3. Availability is checked against the locked value.
4. The quantity/reservation delta is applied and an immutable `stock_movements` row is inserted.
5. Only after commit are the dependent caches invalidated.

`StockMovement` has `updating`/`deleting` guards, so a movement can only ever be inserted.

---

## 4. Query optimisation decisions

| Requirement | Implementation |
|-------------|----------------|
| No raw SQL | Everything uses Eloquent/query builder. `selectRaw()` is used **only** for the `SUM(CASE WHEN ...)` aggregation in the movement summary — the query builder has no typed equivalent for conditional aggregation. |
| DB-level aggregation | `SUM` via `withSum()` for product/summary totals; `SUM(CASE ...)` for the movement summary; `GROUP BY` is not needed because correlated sub-selects keep the summary to a single query. |
| Single summary query | `InventorySummaryService` issues exactly **one** SQL statement: three correlated `SUM` subqueries and two correlated subqueries for the highest-stock warehouse. PHP only renames fields. |
| Eager loading | `with(['category', 'stocks.warehouse'])` is used on product listing and low-stock. `with()` (not `load()`) is chosen so relations are fetched in the same query phase as the parent, producing a fixed number of queries regardless of page size. |
| Query scopes | `active`, `inCategory`, `inWarehouse`, `available`, `priceBetween`, `search`, `sortBy`, `betweenDates`, `belowThreshold` live on the models. |
| Paginated lists | Products (15), movements (20), low-stock (20). |
| Generated column | `stock.available_quantity` is a stored generated column (`quantity - reserved_quantity`), indexed, so availability filtering and sorting stay index-friendly and cannot drift from their source columns. |

---

## 5. Caching strategy

**Driver:** Redis (`CACHE_STORE=redis`). A `cache` Redis connection is used with its own DB index.

### Key naming convention

```
{domain}:{resource}:{version}:{scope}:{parameters...}
```

Lowercase, colon separated, versioned with `v1` so a deploy can bust a whole family:

| Key | TTL | Set by | Invalidated by |
|-----|-----|--------|----------------|
| `categories:tree:v1` | 3600 s (1 h) | `CategoryTreeService` | Automatically on any category create / update / delete |
| `products:list:v1:v{version}:{hash-of-filters}` | 60 s (1 min) | `ProductFilterService` | Every stock adjustment and any category write (version counter bumped) |
| `inventory:summary:v1` | 300 s (5 min) | `InventorySummaryService` | Every successful stock adjustment |
| `inventory:low-stock:v1:v{version}:t{threshold}:p{perPage}` | 300 s (5 min) | `LowStockService` | Every successful stock adjustment (version counter is incremented) |
| `inventory:low-stock:v1:version` | no TTL | `CacheKeys` | Incremented on every successful stock adjustment |

All keys and TTLs are centralised in `app/Support/Cache/CacheKeys.php`. Empty result sets and
error responses are **never** cached.

**Scope safety:** low-stock keys include both the threshold and page size. Product-list keys
include **every** accepted filter (category, warehouse, availability, price range, search, sort,
direction, per-page) **plus the page number**, hashed after sorting the keys. Two different
filter/page combinations can never return the same cached result. All listing keys also carry a
version counter that is bumped after every stock write, so availability filters never serve stale
data.

**Cache warming:** `php artisan cache:warm` primes `categories:tree:v1` and
`inventory:summary:v1`. The category tree is the primary target: it is tiny, read on almost
every page load and changes very rarely, giving the best hit-rate for the least work. The
summary is warmed too because it is the hottest report.

**Invalidation on write:** `CacheKeys::invalidateInventory()` forgets the summary key and bumps
the low-stock version after the stock transaction commits. The `Category` model has `saved` and
`deleted` hooks that automatically forget `categories:tree:v1`, so any category write keeps the
tree fresh (there are no category write endpoints in this assessment, but the rule is enforced at
the model level).

---

## 6. Indexing decisions

Every index is declared in the **same migration** as its column, with an explanatory comment.

| Table | Index / constraint | Reason |
|-------|--------------------|--------|
| `categories` | `slug` UNIQUE | Stable lookup and guaranteed unique slugs. |
| `categories` | `parent_id` FK + index (implicit) + `is_active` | Tree traversal and active-only filtering. |
| `products` | `sku` UNIQUE | Business identifier uniqueness. |
| `products` | `category_id` FK | Enforces referential integrity; used by category filter. |
| `products` | `(category_id, is_active)` | Composite filter for active products in a category subtree. |
| `products` | `base_price` | Price-range filter and price sorting. |
| `warehouses` | `is_active` | Active-warehouse filtering. |
| `stock` | `(product_id, warehouse_id)` UNIQUE | The concurrency invariant; enables safe row locking / upsert. |
| `stock` | `available_quantity` | Low-stock filter and available-stock ordering. |
| `stock` | `product_id`, `warehouse_id` FKs | Referential integrity. |
| `stock_movements` | `(product_id, moved_at)` | Movement history filter + chronological ordering. |
| `stock_movements` | `(warehouse_id, moved_at)` | Warehouse-scoped movement reports. |
| `stock_movements` | `movement_type` | Conditional aggregation groups on this column. |
| `stock_movements` | `reference_type`, `reference_id` (morphs index) | Polymorphic source lookups. |

`stock.quantity`, `stock.reserved_quantity` are `unsignedBigInteger`; `base_price` is
`decimal(12,2)`; booleans, integers, decimals and dates are all cast in the models.

---

## 7. Architecture

```
app/
  Console/Commands/WarmCaches.php        # cache warming
  Enums/MovementType.php                 # movement type constants + helpers
  Exceptions/InsufficientStockException  # 422 business-rule response
  Http/Requests/                         # all input validation
  Http/Resources/                        # every response shape
  Http/Controllers/Api/                  # thin controllers
  Models/                                # casts, relations, query scopes
  Services/                              # all business logic and DB transactions
  Support/Cache/CacheKeys.php            # keys + TTLs
database/migrations/                     # 5 migrations, comments justify every index
database/seeders/                        # realistic data + required edge cases
tests/Feature/                           # endpoint behaviour coverage
```

Business logic never lives in controllers. Controllers only call a service and return an API
Resource; no raw arrays are returned from controllers.

---

## 8. Error handling

- Validation failures → **422** with field-level `errors`.
- Business rules (insufficient stock, over-release) → **422** with a plain-English `message`.
- Unknown routes render JSON (`shouldRenderJsonWhen` for `api/*`).
- Unexpected errors → **500**; with `APP_DEBUG=false` no stack trace or internals are returned.

---

## 9. Assumptions

These points were left open by the brief; here is the decision taken for each.

1. **Missing stock row** — created lazily with zero quantity inside the adjustment transaction.
2. **Uniqueness** — one `stock` row per `(product, warehouse)` pair, enforced by a DB unique key.
3. **Quantity type** — integer counts (`unsignedBigInteger`), never decimals.
4. **Money** — `base_price` is `decimal(12,2)`.
5. **Movement types** — backed enum; `reservation` and `stock_out` reduce availability,
   `reservation_release` and `stock_in` increase it.
6. **Movement log immutability** — inserts only; `updated_at` is deliberately absent, `created_at`
   is an audit column and `moved_at` is the business event time used by date filters.
7. **Category promotion** — an active child of an inactive category is re-parented to the deepest
   active ancestor; if no active ancestor exists it becomes a root node.
8. **Highest-stock warehouse tie-break** — lowest warehouse id (deterministic output).
9. **Zero-stock flag** — `is_zero_stock` is true when total quantity across all warehouses is 0.
10. **Movement summary** — `stock_in` + `reservation_release` count as "in"; `stock_out` +
    `reservation` count as "out"; net = in − out.
11. **Cache warming target** — category tree (primary) and inventory summary (secondary).
12. **Predis** — added because this environment has no `phpredis` extension; Redis remains the
    cache driver in every environment.
13. **Inventory summary paging** — requirement 4.4 asks for a *full* cached summary "across all
    products and warehouses", so this report is intentionally not paginated. All true list
    endpoints (products, movement history, low-stock) are paginated.

---

## 10. Submission checklist

- `.env` is gitignored; `.env.example` contains all keys with placeholders.
- No unused stubs, commented-out code or magic strings (enums/`CacheKeys` instead).
- `php artisan migrate:fresh --seed`, `php artisan cache:warm`, `php artisan serve` and
  `php artisan test` all succeed from a clean checkout.
