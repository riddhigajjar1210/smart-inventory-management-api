<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;

class InventorySummaryService
{
    /**
     * Full-response caching. Empty results are never cached.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(): array
    {
        $key = CacheKeys::inventorySummary();

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        // ONE SQL statement. All SUM work is done by MySQL through withSum()
        // correlated subqueries; the highest-stock warehouse is a correlated
        // subquery as well. No PHP loop performs any aggregation.
        $highestQuantity = Stock::query()
            ->select('stock.quantity')
            ->whereColumn('stock.product_id', 'products.id')
            ->orderByDesc('stock.quantity')
            ->orderBy('stock.warehouse_id')
            ->limit(1);

        $highestWarehouse = Warehouse::query()
            ->join('stock', 'stock.warehouse_id', '=', 'warehouses.id')
            ->select('warehouses.name')
            ->whereColumn('stock.product_id', 'products.id')
            ->orderByDesc('stock.quantity')
            ->orderBy('warehouses.id')
            ->limit(1);

        $products = Product::query()
            ->select(['products.id', 'products.name', 'products.sku'])
            ->withSum('stocks', 'quantity')
            ->withSum('stocks', 'reserved_quantity')
            ->withSum('stocks', 'available_quantity')
            ->selectSub($highestQuantity, 'top_warehouse_quantity')
            ->selectSub($highestWarehouse, 'top_warehouse_name')
            ->orderBy('products.id')
            ->get();

        // Mapping only renames fields and applies the presentation flag.
        $summary = $products->map(static function (Product $product): array {
            $total = (int) ($product->stocks_sum_quantity ?? 0);
            $topName = $product->top_warehouse_name;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'total_quantity' => $total,
                'total_reserved' => (int) ($product->stocks_sum_reserved_quantity ?? 0),
                'available_quantity' => (int) ($product->stocks_sum_available_quantity ?? 0),
                'is_zero_stock' => $total === 0,
                'highest_stock_warehouse' => $topName === null ? null : [
                    'name' => $topName,
                    'quantity' => (int) $product->top_warehouse_quantity,
                ],
            ];
        })->all();

        if ($summary === []) {
            return $summary;
        }

        Cache::put($key, $summary, CacheKeys::TTL_INVENTORY_SUMMARY);

        return $summary;
    }
}
