<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'base_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Include products from the chosen category and all of its descendants.
     */
    public function scopeInCategory(Builder $query, Category $category): Builder
    {
        return $query->whereIn('category_id', $category->descendantIds());
    }

    /**
     * Warehouse and availability can be combined inside one EXISTS clause,
     * guaranteeing the SAME stock row satisfies both conditions.
     */
    public function scopeInWarehouse(Builder $query, int $warehouseId, bool $onlyAvailable = false): Builder
    {
        return $query->whereHas('stocks', function (Builder $stock) use ($warehouseId, $onlyAvailable): void {
            $stock->where('warehouse_id', $warehouseId);

            if ($onlyAvailable) {
                $stock->where('available_quantity', '>', 0);
            }
        });
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereHas('stocks', function (Builder $stock): void {
            $stock->where('available_quantity', '>', 0);
        });
    }

    public function scopePriceBetween(Builder $query, ?float $min, ?float $max): Builder
    {
        if ($min !== null) {
            $query->where('base_price', '>=', $min);
        }

        if ($max !== null) {
            $query->where('base_price', '<=', $max);
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $needle = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($needle): void {
            $inner->where('name', 'like', $needle)
                ->orWhere('sku', 'like', $needle);
        });
    }

    /**
     * Sorting by total available stock uses the `stocks_sum_available_quantity`
     * aggregate selected with withSum() by ProductFilterService (DB-level, no PHP sort).
     */
    public function scopeSortBy(Builder $query, string $field, string $direction): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return match ($field) {
            'price' => $query->orderBy('base_price', $direction)->orderBy('id', $direction),
            'available' => $query->orderBy('stocks_sum_available_quantity', $direction)->orderBy('id', $direction),
            default => $query->orderBy('name', $direction)->orderBy('id', $direction),
        };
    }
}
