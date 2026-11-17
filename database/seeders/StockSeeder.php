<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->orderBy('id')->get();
        $warehouseIds = Warehouse::query()->orderBy('id')->pluck('id')->all();

        foreach ($products as $index => $product) {
            $position = $index + 1;
            $primary = $warehouseIds[$position % count($warehouseIds)];

            $combinations = [$primary];

            // Products spread across multiple warehouses (edge case).
            if ($position % 4 === 0) {
                $combinations[] = $warehouseIds[($position + 1) % count($warehouseIds)];
            }

            if ($position % 7 === 0) {
                $combinations[] = $warehouseIds[($position + 2) % count($warehouseIds)];
            }

            foreach ($combinations as $warehouseId) {
                // Product #1 is the zero-stock edge case.
                $quantity = $position === 1 ? 0 : (($position * 37) % 420);
                $reserved = $position === 1 ? 0 : min($quantity, ($position * 5) % 31);

                Stock::query()->updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                    [
                        'quantity' => $quantity,
                        'reserved_quantity' => $reserved,
                    ],
                );
            }
        }
    }
}
