<?php

namespace App\Services;

use App\Models\Stock;
use App\Support\Cache\CacheKeys;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class LowStockService
{
    /**
     * Cache is scoped by threshold AND page size, and version-bumped on every
     * successful stock adjustment. Empty result sets are not cached.
     */
    public function lowStock(int $threshold, int $perPage): LengthAwarePaginator
    {
        $key = CacheKeys::lowStock($threshold, $perPage);

        $cached = Cache::get($key);
        if ($cached instanceof LengthAwarePaginator) {
            return $cached;
        }

        $stocks = Stock::query()
            ->with(['product', 'warehouse'])
            ->belowThreshold($threshold)
            ->orderBy('available_quantity')
            ->orderBy('id')
            ->paginate($perPage);

        if ($stocks->isEmpty()) {
            return $stocks;
        }

        Cache::put($key, $stocks, CacheKeys::TTL_LOW_STOCK);

        return $stocks;
    }
}
