<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Support\Cache\CacheKeys;
use App\Support\Defaults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProductFilterService
{
    /**
     * Every filter is applied through a model scope, so all filters combine in
     * one SQL statement regardless of the order or combination requested.
     *
     * The full result (including page number) is cached under a key derived
     * from the exact filter set, so different combinations never share data.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $key = CacheKeys::productList($filters);

        $cached = Cache::get($key);
        if ($cached instanceof LengthAwarePaginator) {
            return $cached;
        }

        $query = Product::query()->active();

        if (isset($filters['category'])) {
            $category = Category::query()->findOrFail((int) $filters['category']);
            $query->inCategory($category);
        }

        $warehouse = isset($filters['warehouse']) ? (int) $filters['warehouse'] : null;
        $available = isset($filters['available'])
            && filter_var($filters['available'], FILTER_VALIDATE_BOOLEAN);

        // Both conditions inside one whereHas() => the same stock row must match.
        if ($warehouse !== null) {
            $query->inWarehouse($warehouse, $available);
        } elseif ($available) {
            $query->available();
        }

        $query->priceBetween(
            isset($filters['min_price']) ? (float) $filters['min_price'] : null,
            isset($filters['max_price']) ? (float) $filters['max_price'] : null,
        );

        $query->search($filters['search'] ?? null);

        // Eager load every relation used by ProductResource (no N+1).
        // Aggregates are computed by the database via correlated subqueries.
        $query->with(['category', 'stocks.warehouse'])
            ->withSum('stocks', 'quantity')
            ->withSum('stocks', 'reserved_quantity')
            ->withSum('stocks', 'available_quantity');

        $query->sortBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc');

        $result = $query->paginate(
            (int) ($filters['per_page'] ?? Defaults::PRODUCTS_PER_PAGE),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );

        // Empty result sets are deliberately not cached.
        if ($result->isEmpty()) {
            return $result;
        }

        Cache::put($key, $result, CacheKeys::TTL_PRODUCT_LIST);

        return $result;
    }
}
