# Smart Inventory Management API

A Laravel REST API for managing products, categories, warehouses, stock, and stock movements.
It focuses on correct stock updates, fast queries, and simple JSON responses.

## Technology Stack

- PHP 8.2
- Laravel 11
- MySQL 8 / MariaDB
- Redis with Predis
- Eloquent ORM
- Postman for API testing

## Features

- Nested category tree with active and inactive categories.
- Product list with category, warehouse, availability, price, and search filters.
- Warehouse-wise stock management.
- Stock adjustments with transactions and row-level locking.
- Inventory summary with database-level aggregation.
- Product movement history with date filters.
- Low-stock alerts with a configurable threshold.
- Redis caching with automatic invalidation.
- Form Request validation and API Resource responses.

## Database Structure

The application uses five main tables:

| Table | Purpose |
|---|---|
| categories | Stores the category hierarchy. |
| products | Stores product details and SKU. |
| warehouses | Stores warehouse information. |
| stock | Stores warehouse-wise stock quantities. |
| stock_movements | Stores the stock adjustment history. |

## Setup Instructions

Start MySQL in XAMPP before running these steps.

**1. Clone the repository**

```bash
git clone https://github.com/riddhigajjar1210/smart-inventory-management-api.git
cd smart-inventory-management-api
```

**2. Install dependencies**

```bash
composer install
```

**3. Configure the environment**

```powershell
Copy-Item .env.example .env -Force
php artisan key:generate
```

Update MySQL and Redis settings in `.env` if needed.

**4. Create the databases**

```sql
CREATE DATABASE IF NOT EXISTS logieagle_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS logieagle_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**5. Run migrations and seeders**

```bash
php artisan migrate:fresh --seed --force
```

**6. Start Redis and warm the cache**

```bash
redis-cli ping
php artisan cache:warm
```

`redis-cli ping` should return `PONG`.

**7. Start the application**

```bash
php artisan serve
```

Base URL:

```text
http://127.0.0.1:8000/api
```

## API Reference

All responses are JSON.

| Method | Endpoint | Parameters | Returns |
|---|---|---|---|
| GET | `/api/categories/tree` | None | Nested category tree |
| GET | `/api/products` | category, warehouse, available, min_price, max_price, search, sort, direction, per_page | Paginated products |
| POST | `/api/stock/adjust` | product_id, warehouse_id, movement_type, quantity | Updated stock row |
| GET | `/api/inventory/summary` | None | Stock summary per product |
| GET | `/api/products/{id}/movements` | date_from, date_to, per_page | Movement list and summary |
| GET | `/api/inventory/low-stock` | threshold, per_page | Low-stock products |

## Stock Management

The application supports four movement types:

- `stock_in`
- `stock_out`
- `reservation`
- `reservation_release`

Available quantity is calculated as:

```text
Available Quantity = Quantity - Reserved Quantity
```

Database transactions and `lockForUpdate()` keep stock correct during simultaneous updates.
Every successful adjustment is recorded in the stock movement history.

## Caching Strategy

Redis is used to cache frequently accessed API responses.

- Category tree: 1 hour.
- Product listing: 1 minute.
- Inventory summary: 5 minutes.
- Low-stock results: 5 minutes.

Cache keys include relevant filters and parameters.
Related caches are cleared after successful database updates.
Empty results and errors are not cached.

The `php artisan cache:warm` command prepares the category tree and inventory summary caches.

## Indexing Decisions

| Table | Index | Reason |
|---|---|---|
| categories | slug UNIQUE | Keeps slugs unique. |
| products | sku UNIQUE | Keeps SKUs unique. |
| stock | product_id + warehouse_id UNIQUE | Makes row locking safe. |
| stock | available_quantity | Speeds up low-stock queries. |
| stock_movements | product_id + moved_at | Speeds up movement history. |
| stock_movements | movement_type | Speeds up movement totals. |

Foreign keys are also indexed for joins and lookups.

## Query Optimization

- Eloquent relationships and eager loading.
- Database-level aggregation with SUM.
- Reusable model query scopes.
- Pagination for all list endpoints.
- Indexes on filter and join columns.
- Generated available-quantity column.
- Database transactions for stock updates.

## Sample Data

The seeders provide:

- 12 categories with nested relationships.
- 50 products.
- 3 warehouses.
- About 150 stock records.
- 200 stock movement records.

The data also includes zero-stock products, reserved quantities, inactive categories, and products across multiple warehouses.

## Testing

Run the test suite:

```bash
php artisan test
```

Expected result:

```text
Tests: 16 passed
```

Open these URLs in a browser to test the API:

```text
http://127.0.0.1:8000/api/categories/tree
http://127.0.0.1:8000/api/products
http://127.0.0.1:8000/api/inventory/summary
http://127.0.0.1:8000/api/inventory/low-stock?threshold=50
http://127.0.0.1:8000/api/products/1/movements
```

On Windows, you can also double-click `TEST_PRACTICAL.bat`.
It sets up the database, runs the tests, and starts the API.

## Error Handling

- `422` – Validation errors and business rule violations.
- `404` – Requested resource not found.
- `500` – Unexpected server errors.

API responses use a consistent JSON structure.

## Assumptions

- One stock row exists per product and warehouse.
- Quantities are whole numbers.
- Prices use two decimal places.
- Movement records are never edited or deleted.
- The inventory summary is not paginated because it is a full report.
- Predis is used because the PHP Redis extension is not required.

## GitHub Repository

[Smart Inventory Management API](https://github.com/riddhigajjar1210/smart-inventory-management-api)

**Note:** Do not upload your actual `.env` file. Use `.env.example` for environment configuration.
