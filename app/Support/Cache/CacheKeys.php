<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for every cache key and TTL in the application.
 *
 * Convention (documented in README):
 *   {domain}:{resource}:{version}:{scope}:{parameters}
 *
 * All keys are lowercase and colon separated. A version segment lets a
 * deployment invalidate an entire family without scanning Redis.
 */
final class CacheKeys
{
    public const VERSION = 'v1';

    public const TTL_CATEGORY_TREE = 3600;      // 1 hour — rarely changes.

    public const TTL_INVENTORY_SUMMARY = 300;   // 5 minutes — hot read path.

    public const TTL_LOW_STOCK = 300;           // 5 minutes — threshold scoped.

    public const TTL_PRODUCT_LIST = 60;         // 1 minute — filter/page scoped.

    public static function categoryTree(): string
    {
        return sprintf('categories:tree:%s', self::VERSION);
    }

    public static function inventorySummary(): string
    {
        return sprintf('inventory:summary:%s', self::VERSION);
    }

    /**
     * Low-stock results are cached per threshold. A version counter is bumped
     * on every write so all threshold variants expire together without
     * needing to know which thresholds were previously requested.
     */
    public static function lowStock(int $threshold, int $perPage): string
    {
        return sprintf(
            'inventory:low-stock:%s:v%d:t%d:p%d',
            self::VERSION,
            self::lowStockVersion(),
            $threshold,
            $perPage,
        );
    }

    /**
     * Product listing is cached per exact filter combination AND page number,
     * so two different requests can never share one cached result.
     */
    public static function productList(array $filters): string
    {
        ksort($filters);

        return sprintf(
            'products:list:%s:v%d:%s',
            self::VERSION,
            self::productListVersion(),
            md5(json_encode($filters) ?: ''),
        );
    }

    public static function productListVersionKey(): string
    {
        return sprintf('products:list:%s:version', self::VERSION);
    }

    public static function productListVersion(): int
    {
        Cache::add(self::productListVersionKey(), 1);

        return (int) Cache::get(self::productListVersionKey(), 1);
    }

    public static function bumpProductListVersion(): void
    {
        Cache::increment(self::productListVersionKey());
    }

    public static function lowStockVersionKey(): string
    {
        return sprintf('inventory:low-stock:%s:version', self::VERSION);
    }

    public static function lowStockVersion(): int
    {
        // Seed the counter once so the first bump always changes the value.
        Cache::add(self::lowStockVersionKey(), 1);

        return (int) Cache::get(self::lowStockVersionKey(), 1);
    }

    public static function bumpLowStockVersion(): void
    {
        Cache::increment(self::lowStockVersionKey());
    }

    /**
     * Called after any successful stock adjustment.
     */
    public static function invalidateInventory(): void
    {
        Cache::forget(self::inventorySummary());
        self::bumpLowStockVersion();
        self::bumpProductListVersion();
    }
}
