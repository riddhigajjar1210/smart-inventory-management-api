# Implementation Plan — Smart Inventory Management API (Laravel Practical Assessment)

> Source brief: `C:\Users\Excel\Downloads\Laravel Task.pdf`
> Stack: PHP 8.1+, Laravel 10 or 11, MySQL 8, Redis
> Submission window: 1–2 business days · Deliverable: public/shared GitHub or GitLab repo link

---

## 0. Critical review of the brief (ambiguities to resolve as assumptions)

These are not mistakes in the brief — they are deliberately open decisions. Each one should be
written down in the README **Assumptions** section.

| # | Open point | Recommended call | Why |
|---|-----------|------------------|-----|
| 1 | `stock` row may not exist for a product/warehouse pair | Create it lazily inside the adjust transaction (upsert with `quantity = 0`) | Keeps adjust idempotent per pair and avoids null handling in reports |
| 2 | Unique constraint on `stock(product_id, warehouse_id)` | Yes — composite unique | Required for correct concurrent upserts and the DB-level locking strategy |
| 3 | `quantity` data type | `unsignedBigInteger` | Stock is an integer count; prevents negative values at DB level too |
| 4 | `base_price` type | `decimal(12,2)` | Money must never be float |
| 5 | `movement_type` values | PHP 8.1 backed enum `MovementType` | Brief explicitly asks to remove magic strings |
| 6 | `reference_id` / `reference_type` nullable | Yes (polymorphic, nullable `morphs`) | Not every adjustment has an external document |
| 7 | `stock_movements` "immutable log" | No `updated_at`; no update/delete routes; DB-level guard optional | Requirement says immutable; model should only expose `create` |
| 8 | `moved_at` vs `created_at` | Keep both; `moved_at` is the business event time, `created_at` the audit time | Date filters should use `moved_at` |
| 9 | Category "nearest active ancestor" promotion | Re-parent active orphan subtrees to the deepest active ancestor during tree build | Explicit requirement in 4.1 |
| 10 | "Warehouse with highest stock" tie-break | Lowest `warehouse_id` (documented) | Deterministic output |
| 11 | Cache warming target | `GET /api/categories/tree` | Small, changes rarely, read on almost every page load |
| 12 | Availability sort with stock in another table | Sort by a `withAggregate`-style subquery / join on `stock` | Must be DB-level, no PHP loops |

---

## 1. Delivery timeline (2-day plan)

### Day 1 — foundation + core writes
1. Scaffold Laravel app, configure `.env.example` (MySQL + Redis), `.gitignore`, `README.md` skeleton.
2. Migrations (all 5 tables) with indexes + explanatory comments **in the same migration**.
3. Models: casts, enums, relationships, query scopes.
4. Form Requests for every input endpoint.
5. API Resources for every response.
6. Seeders with realistic + edge-case data.
7. `POST /api/stock/adjust` with `DB::transaction`, `lockForUpdate`, immutable movement insert.
8. Verify concurrency behavior (two parallel requests, same product/warehouse).

### Day 2 — reads, caching, polish
9. `GET /api/categories/tree` (efficient build + inactive handling + cache + warming).
10. `GET /api/products` (all filters combinable, scopes, eager loading, pagination, sorting).
11. `GET /api/inventory/summary` (single aggregated query + caching + invalidation).
12. `GET /api/products/{id}/movements` (pagination + DB-level summary + date filters).
13. `GET /api/inventory/low-stock` (threshold, ordering, scoped cache key, invalidation).
14. Global exception handler → consistent JSON errors (422 / 500, no stack traces).
15. Cache key convention, TTLs, invalidation map, warming command documented in README.
16. Full README (setup, API reference, caching, indexing, assumptions).
17. `php artisan migrate:fresh --seed`, `php artisan serve`, smoke-test every endpoint.

---

## 2. Data model & migrations

| Table | Columns (minimum from brief) | Added keys / constraints | Reason (comment in migration) |
|-------|------------------------------|--------------------------|-------------------------------|
| `categories` | id, name, parent_id NULL, slug UNIQUE, is_active, timestamps | FK `parent_id → categories.id` (null on delete); index `is_active`; index `parent_id` | Tree traversal + filtering active nodes |
| `products` | id, category_id, name, sku UNIQUE, description, base_price `decimal(12,2)`, is_active, timestamps | FK `category_id`; indexes `sku`, `is_active`, `base_price` | Unique SKU; filter/sort paths |
| `warehouses` | id, name, city, is_active, timestamps | index `is_active` | Active filtering |
| `stock` | id, product_id, warehouse_id, quantity, reserved_quantity unsigned, timestamps | **Composite UNIQUE** `(product_id, warehouse_id)`; FKs; index `(quantity - reserved_quantity)` via generated column or index on both columns | Concurrency-safe upsert + availability filters |
| `stock_movements` | id, product_id, warehouse_id, movement_type enum/string, quantity, reference_id, reference_type, note, moved_at | FKs; indexes `(product_id, moved_at)`, `(warehouse_id, moved_at)`, `movement_type` | Movement history + summary aggregation filters |

Notes:
- No `updated_at` on `stock_movements` (immutable log).
- Use `timestamps` on `categories/products/warehouses/stock` as required.
- Every index lives in the **same migration** as its column (explicit brief rule).

---

## 3. Architecture / file layout

```
app/
  Enums/MovementType.php
  Models/{Category,Product,Warehouse,Stock,StockMovement}.php
  Http/Requests/{AdjustStockRequest,IndexProductRequest,MovementHistoryRequest,LowStockRequest}.php
  Http/Resources/{CategoryTreeResource,ProductResource,InventorySummaryResource,
                  MovementHistoryResource,StockMovementResource,LowStockResource}.php
  Http/Controllers/Api/{CategoryController,ProductController,StockController,
                        InventoryController}.php   # thin
  Services/{CategoryTreeService,ProductFilterService,StockAdjustmentService,
            InventorySummaryService,MovementHistoryService,LowStockService}.php
  Support/Cache/CacheKeys.php       # key convention as constants + builder methods
  Console/Commands/WarmCaches.php    # cache warming
database/seeders/{CategorySeeder,ProductSeeder,WarehouseSeeder,StockSeeder,StockMovementSeeder}.php
tests/Feature/...                    # at least concurrency + each endpoint smoke test
```

Rules: controllers only call a Service/Action and return a Resource; **no raw arrays**; all
input via Form Requests; all writes wrapped in `DB::transaction`; casts declared on models.

---

## 4. Endpoint implementation map

### 4.1 `GET /api/categories/tree`
- Fetch **all** categories in one query (`select id,name,parent_id,is_active…`), build tree in PHP with
  a single pass keyed by id — no recursive DB calls, no N+1.
- Drop inactive nodes; re-parent active children of an inactive node to nearest active ancestor.
- Cache: `Cache::remember(CacheKeys::CATEGORY_TREE, 3600, …)` (1 h). Invalidate on any
  category create/update/delete/active toggle.
- Warm this endpoint via `WarmCaches` command (small, read very often, changes rarely).

### 4.2 `GET /api/products`
- One query with query scopes: `scopeInCategory()` (include descendants via recursive CTE-free
  breadth-first id list built in one query), `scopeInWarehouse()`, `scopeAvailable()`,
  `scopePriceBetween()`, `scopeSearch()`, `scopeSortBy()`.
- Eager load relations with `with()` (not `load()`) using `withCount`/`withSum`/`withAggregate` for
  stock-derived columns — justify in README: avoids N+1 and computes availability in SQL.
- `paginate(15)`; sort by `name`, `base_price`, or available stock (subquery in `orderBy`).
- All filters combine via chained scopes; no caching required here (but if added, key must include
  every filter + page + sort).

### 4.3 `POST /api/stock/adjust`
- Movement types via `MovementType` enum: `stock_in`, `stock_out`, `reservation`, `reservation_release`.
- Inside `DB::transaction()`:
  1. `Stock::where(product_id, warehouse_id)->lockForUpdate()->firstOrCreate(…)` — row-level lock
     serialises concurrent adjustments (DB-level fix, not PHP).
  2. Validate business rule: for `stock_out`/`reservation`, `quantity - reserved - requested >= 0`,
     else throw a domain exception → 422 with plain-English message.
  3. Apply delta to `quantity` or `reserved_quantity`.
  4. Insert immutable `stock_movements` row with `moved_at = now()`.
- Return updated available quantity via `StockResource`.
- After commit: invalidate `inventory:summary:*` and `inventory:low-stock:*` keys.

### 4.4 `GET /api/inventory/summary`
- **Single query**: `Product::query()->leftJoin('stock',…)->groupBy('products.id')
  ->selectRaw('SUM(stock.quantity) total_quantity, SUM(stock.reserved_quantity) total_reserved,
  …')` plus a correlated subquery/join for highest-stock warehouse. No PHP loops.
- Zero-stock products included with `is_zero_stock` flag in the Resource.
- Cache full response: `Cache::remember(CacheKeys::INVENTORY_SUMMARY, 300, …)` (5 min TTL).
- Invalidate **on every successful stock adjustment** (same code path as 4.3).

### 4.5 `GET /api/products/{id}/movements`
- `StockMovement::where('product_id',$id)->when(date_from/date_to, …)->paginate(20)`.
- Summary block computed in DB: `selectRaw('SUM(CASE WHEN movement_type IN (stock_in,
  reservation_release) THEN quantity ELSE 0 END) total_in, … total_out, … net_movement')`
  over the same filters — no PHP aggregation.
- Wrap rows + summary in `MovementHistoryResource`.

### 4.6 `GET /api/inventory/low-stock`
- Query `stock` joined to `products`/`warehouses`, filter `(quantity - reserved_quantity) < threshold`
  (default 10), order by available ASC.
- Fields: product name, SKU, warehouse name, quantity, reserved, available.
- Cache key includes threshold → `inventory:low-stock:t:{threshold}`; TTL 300 s.
- Invalidate on successful stock adjustment (same invalidation service).

---

## 5. Caching strategy (document verbatim in README)

- **Driver:** Redis (`CACHE_DRIVER=redis`, `REDIS_*` in `.env.example`).
- **Key convention:** `{domain}:{resource}:{scope...}:{params...}` all lowercase, colon-separated,
  e.g. `categories:tree:v1`, `inventory:summary:v1`, `inventory:low-stock:v1:t10`.
- **Version suffix `v1`** allows global bust after a deploy/schema change.
- **Scoping:** every filter, threshold, sort, page, and id is hashed/embedded in the key.
- **TTLs:** tree 3600 s; summary 300 s; low-stock 300 s.
- **Invalidation map:**

| Cached endpoint | Invalidated by |
|-----------------|----------------|
| categories tree | category create/update/delete/is_active change |
| inventory summary | every successful stock adjustment |
| low-stock | every successful stock adjustment |

- **Warming:** `php artisan cache:warm` primes `categories:tree:v1` at deploy. Reason: read on
  nearly every request, tiny payload, changes rarely — best hit-rate/effort ratio.
- **Never cache** 4xx/5xx responses or empty result sets (`if ($result->isEmpty()) return` before
  `Cache::remember`).

---

## 6. Query-optimisation requirements — compliance check

- [x] No raw SQL; query builder / Eloquent express everything.
- [x] `SUM`, `CASE`, `GROUP BY` used in summary + movement history.
- [x] All response relations eager-loaded with `with()`; justification in README.
- [x] Reusable filters live as model scopes.
- [x] Indexes declared in the same migration as the column.
- [x] Every list endpoint paginated (products 15, movements 20, low-stock paginated).

---

## 7. Code quality & error handling — compliance check

- [x] Service/Action classes hold business logic; controllers thin.
- [x] Form Requests validate all input.
- [x] API Resources for every response; no raw arrays.
- [x] Model casts: `is_active` → bool, quantities/ids → int/unsigned, `base_price` → decimal,
      `moved_at` → datetime.
- [x] All multi-step writes wrapped in `DB::transaction`.
- [x] Global handler: validation 422 + field detail; business rule 422 + plain message;
      unexpected 500 with no stack trace in production (`APP_DEBUG=false`).

---

## 8. Seed data (realistic + edge cases)

- 3+ categories with nested children (and ≥1 category with **no products**).
- 50 products across categories; one with **zero stock**.
- 3 warehouses; at least one product stocked in **multiple warehouses**.
- 200 stock movements covering all 4 movement types and a range of `moved_at` dates.

---

## 9. Submission checklist

1. Code pushed to GitHub/GitLab; final working code on `main`/`master`.
2. `.env` NOT committed; `.env.example` present with placeholder values for all keys.
3. README contains: Setup Instructions, API Reference table, Caching Strategy, Indexing Decisions,
   Assumptions.
4. No extra packages; no empty stubs from `make:model -a`; no commented-out code or unused methods.
5. All magic values replaced by constants/enums.
6. `php artisan serve` boots cleanly after following README from scratch.

---

## 10. Highest-risk items (spend the most time here)

1. **Concurrency on stock adjust** — must be a DB-level row lock (`lockForUpdate`) on a unique
   `(product_id, warehouse_id)` stock row. This is the #1 evaluation focus.
2. **Single-query inventory summary** — reviewers read the SQL; avoid any PHP aggregation loop.
3. **Category tree with inactive-node promotion** — easy to get subtly wrong.
4. **Cache invalidation on write** — stale data after adjust is an automatic fail.
5. **All product filters combining** — especially category descendants + availability + sort by
   available stock.
