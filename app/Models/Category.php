<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Support\Cache\CacheKeys;

class Category extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Any category mutation makes the cached tree stale, so drop it automatically.
     */
    protected static function booted(): void
    {
        static::saved(static function (): void {
            Cache::forget(CacheKeys::categoryTree());
            CacheKeys::bumpProductListVersion();
        });

        static::deleted(static function (): void {
            Cache::forget(CacheKeys::categoryTree());
            CacheKeys::bumpProductListVersion();
        });
    }

    /**
     * Ids of this category and every descendant, read with ONE query using an
     * in-memory adjacency map. Used by the product category filter so a parent
     * automatically includes products from all child categories at any depth.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        /** @var Collection<int, Category> $all */
        $all = self::query()->get(['id', 'parent_id']);

        $childrenByParent = $all->groupBy('parent_id');

        $ids = [$this->getKey()];
        $frontier = [$this->getKey()];

        while ($frontier !== []) {
            $next = [];

            foreach ($frontier as $parentId) {
                foreach ($childrenByParent->get($parentId, []) as $child) {
                    /** @var Category $child */
                    $ids[] = $child->getKey();
                    $next[] = $child->getKey();
                }
            }

            $frontier = $next;
        }

        return $ids;
    }
}
