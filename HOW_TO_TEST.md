# How to test this practical task (easy way)

## Step 1 — Start MySQL

Open **XAMPP Control Panel** and click **Start** next to **MySQL**.
(If it is already running, skip this.)

## Step 2 — Double-click one file

In this folder, double-click:

```
TEST_PRACTICAL.bat
```

A black window opens and does everything automatically:

1. Starts Redis
2. Creates the tables and sample data in `logieagle_db`
3. Warms the cache
4. Runs the tests
5. Starts the API and opens your browser

## Step 3 — What you should see

In the black window:

```
Tests:    16 passed (78 assertions)
```

That means everything is correct. If you see red text instead, read the message there.

Your browser will open and show text like this:

```json
{
  "data": [
    { "id": 1, "name": "Electronics", "children": [ ... ] }
  ]
}
```

That is the API working.

## Step 4 — Try the other URLs

In your browser address bar, replace the address with these one at a time:

```
http://127.0.0.1:8000/api/categories/tree
http://127.0.0.1:8000/api/products
http://127.0.0.1:8000/api/inventory/summary
http://127.0.0.1:8000/api/inventory/low-stock?threshold=50
http://127.0.0.1:8000/api/products/1/movements
```

Each one should show JSON text, not an error.

## Step 5 — Test adding stock (POST)

Open a new PowerShell window, paste these two lines, pressing Enter after each:

```powershell
'{"product_id":1,"warehouse_id":2,"movement_type":"stock_in","quantity":10}' | Set-Content "$env:TEMP\adjust.json" -NoNewline
curl.exe -s -X POST -H 'Content-Type: application/json' --data-binary "@$env:TEMP\adjust.json" 'http://127.0.0.1:8000/api/stock/adjust'
```

You should see something like:

```json
{"data":{"product_id":1,"warehouse_id":2,"available_quantity":10}}
```

That means the stock was adjusted successfully.

## If the black window closes immediately

Open PowerShell in this folder and run these commands yourself, one by one:

```powershell
php artisan migrate:fresh --seed --force
php artisan cache:warm
php artisan test
php artisan serve
```

Then open the URLs from Step 4 in your browser.
