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

Follow each step. Do not skip a step.

### Step 1: Start MySQL

Open XAMPP.
Click Start next to MySQL.

### Step 2: Open the terminal

Open PowerShell.
Go to the htdocs folder.

```powershell
cd C:\xampp\htdocs
```

### Step 3: Clone the project

Run this command.

```powershell
git clone https://github.com/riddhigajjar1210/smart-inventory-management-api.git
```

Open the project folder.

```powershell
cd smart-inventory-management-api
```

### Step 4: Install packages

Run this command.

```powershell
composer install
```

### Step 5: Create two databases

Open phpMyAdmin.
Create two databases.
Name one database logieagle_db.
Name one database logieagle_db_test.
Use collation utf8mb4_unicode_ci.

You can also run this SQL.

```sql
CREATE DATABASE IF NOT EXISTS logieagle_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS logieagle_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 6: Create the .env file

Run these two commands.

```powershell
Copy-Item .env.example .env -Force
php artisan key:generate
```

Do not upload the .env file to GitHub.

### Step 7: Start Redis

Start Redis.
Run this command.

```powershell
redis-cli ping
```

You will see PONG.

### Step 8: Create tables and sample data

Run this command.

```powershell
php artisan migrate:fresh --seed --force
```

### Step 9: Warm the cache

Run this command.

```powershell
php artisan cache:warm
```

### Step 10: Start the API

Run this command.

```powershell
php artisan serve
```

Open this link.

http://127.0.0.1:8000/api/categories/tree

### Step 11: Run the tests

Open a new terminal.
Run this command.

```powershell
php artisan test
```

You will see 16 passed.

### Step 12: Easy way

You can double-click TEST_PRACTICAL.bat.
It does steps 7 to 11 for you.

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
