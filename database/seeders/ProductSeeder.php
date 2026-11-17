<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private const TOTAL_PRODUCTS = 50;

    public function run(): void
    {
        $categoryIds = Category::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $adjectives = ['Compact', 'Premium', 'Heavy Duty', 'Ergonomic', 'Wireless', 'Pro', 'Classic', 'Smart'];
        $nouns = ['Notebook', 'Monitor', 'Chair', 'Desk', 'Headset', 'Keyboard', 'Router', 'Cabinet', 'Scanner', 'Lamp'];

        for ($index = 1; $index <= self::TOTAL_PRODUCTS; $index++) {
            $name = sprintf(
                '%s %s %d',
                $adjectives[($index - 1) % count($adjectives)],
                $nouns[($index - 1) % count($nouns)],
                $index,
            );

            Product::query()->updateOrCreate(
                ['sku' => sprintf('SKU-%05d', $index)],
                [
                    'category_id' => $categoryIds[($index - 1) % count($categoryIds)],
                    'name' => $name,
                    'description' => sprintf('Reliable %s for everyday professional use.', strtolower($nouns[($index - 1) % count($nouns)])),
                    'base_price' => round(150 + (($index * 937) % 48000) + 0.99, 2),
                    // A couple of inactive products exercise the active filter.
                    'is_active' => $index % 23 !== 0,
                ],
            );
        }
    }
}
