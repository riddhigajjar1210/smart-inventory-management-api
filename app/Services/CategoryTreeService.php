<?php

namespace App\Services;

use App\Models\Category;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;

class CategoryTreeService
{
    /**
     * Cached full hierarchy. Empty trees are deliberately not cached.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $key = CacheKeys::categoryTree();

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $tree = $this->buildTree();

        if ($tree === []) {
            return $tree;
        }

        Cache::put($key, $tree, CacheKeys::TTL_CATEGORY_TREE);

        return $tree;
    }

    /**
     * Builds the tree with ONE query and an in-memory adjacency map.
     * Inactive nodes vanish, while their active descendants are promoted to
     * the nearest active ancestor (or become roots when there is none).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(): array
    {
        $categories = Category::query()
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'name', 'slug', 'is_active']);

        $childrenByParent = [];

        foreach ($categories as $category) {
            // 0 is the synthetic key for root nodes.
            $childrenByParent[$category->parent_id ?? 0][] = $category;
        }

        $visited = [];

        $walk = function (int $parentKey) use (&$walk, $childrenByParent, &$visited): array {
            $nodes = [];

            foreach ($childrenByParent[$parentKey] ?? [] as $category) {
                // Defensive guard against cyclic data.
                if (isset($visited[$category->id])) {
                    continue;
                }

                $visited[$category->id] = true;

                if (! $category->is_active) {
                    // Skip the inactive node but lift its active subtree up one level.
                    foreach ($walk((int) $category->id) as $promoted) {
                        $nodes[] = $promoted;
                    }

                    continue;
                }

                $nodes[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'is_active' => true,
                    'children' => $walk((int) $category->id),
                ];
            }

            return $nodes;
        };

        return $walk(0);
    }
}
