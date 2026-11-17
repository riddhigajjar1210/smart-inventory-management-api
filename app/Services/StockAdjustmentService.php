<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Support\Cache\CacheKeys;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    /**
     * The row-level lock inside this transaction is the concurrency fix: two
     * requests for the same product/warehouse cannot read the same stale value.
     *
     * @param  array<string, mixed>  $data
     */
    public function adjust(array $data): Stock
    {
        $type = $data['movement_type'] instanceof MovementType
            ? $data['movement_type']
            : MovementType::from($data['movement_type']);

        $productId = (int) $data['product_id'];
        $warehouseId = (int) $data['warehouse_id'];
        $quantity = (int) $data['quantity'];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $stock = DB::transaction(function () use (
                    $type,
                    $productId,
                    $warehouseId,
                    $quantity,
                    $data,
                ): Stock {
                    // Lock the existing stock row. The UNIQUE(product_id, warehouse_id)
                    // key guarantees at most one such row exists.
                    $stock = Stock::query()
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->lockForUpdate()
                        ->first();

                    if ($stock === null) {
                        $stock = $this->createStockRow($productId, $warehouseId);
                    }

                    $available = (int) $stock->available_quantity;

                    if ($type->reducesAvailability() && $available < $quantity) {
                        throw InsufficientStockException::forAdjustment($quantity, $available);
                    }

                    if ($type === MovementType::ReservationRelease
                        && (int) $stock->reserved_quantity < $quantity) {
                        throw InsufficientStockException::forReservationRelease(
                            $quantity,
                            (int) $stock->reserved_quantity,
                        );
                    }

                    $this->applyDelta($stock, $type, $quantity);

                    // Immutable audit log, written in the same transaction.
                    StockMovement::query()->create([
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'movement_type' => $type->value,
                        'quantity' => $quantity,
                        'reference_type' => $data['reference_type'] ?? null,
                        'reference_id' => isset($data['reference_id']) ? (int) $data['reference_id'] : null,
                        'note' => $data['note'] ?? null,
                        'moved_at' => now(),
                    ]);

                    return $stock->refresh();
                });

                // Only after a successful commit: no stale cached report can survive.
                CacheKeys::invalidateInventory();

                return $stock->load('warehouse');
            } catch (QueryException $exception) {
                if ($this->isDuplicateKey($exception) && $attempt < 3) {
                    continue;
                }

                throw $exception;
            }
        }
    }

    /**
     * Two concurrent first-time adjustments can race on the unique key.
     * The loser simply re-reads the row created by the winner, still under lock.
     */
    private function createStockRow(int $productId, int $warehouseId): Stock
    {
        try {
            return Stock::query()->create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            return Stock::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function applyDelta(Stock $stock, MovementType $type, int $quantity): void
    {
        match ($type) {
            MovementType::StockIn => $stock->increment('quantity', $quantity),
            MovementType::StockOut => $stock->decrement('quantity', $quantity),
            MovementType::Reservation => $stock->increment('reserved_quantity', $quantity),
            MovementType::ReservationRelease => $stock->decrement('reserved_quantity', $quantity),
        };
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000'
            || str_contains(strtolower($exception->getMessage()), 'duplicate entry');
    }
}
