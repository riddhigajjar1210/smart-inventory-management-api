<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    private const TOTAL_MOVEMENTS = 200;

    public function run(): void
    {
        $stocks = Stock::query()->get(['id', 'product_id', 'warehouse_id']);

        if ($stocks->isEmpty()) {
            return;
        }

        $types = MovementType::values();
        $notes = [
            'Received against purchase order.',
            'Issued to production floor.',
            'Reserved for customer order.',
            'Reservation cancelled by customer.',
            'Cycle count correction.',
        ];

        for ($i = 0; $i < self::TOTAL_MOVEMENTS; $i++) {
            $stock = $stocks->random();
            $type = MovementType::from($types[$i % count($types)]);
            $withReference = $i % 3 === 0;

            StockMovement::query()->create([
                'product_id' => $stock->product_id,
                'warehouse_id' => $stock->warehouse_id,
                'movement_type' => $type->value,
                'quantity' => 1 + (($i * 13) % 25),
                'reference_type' => $withReference ? 'App\\Models\\Product' : null,
                'reference_id' => $withReference ? $stock->product_id : null,
                'note' => $notes[$i % count($notes)],
                'moved_at' => now()->subDays($i % 90)->subHours($i % 12),
            ]);
        }
    }
}
