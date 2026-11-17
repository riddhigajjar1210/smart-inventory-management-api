<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Support\Defaults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MovementHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{movements: LengthAwarePaginator, summary: array<string, int>}
     */
    public function history(int $productId, array $filters): array
    {
        $from = isset($filters['date_from']) ? (string) $filters['date_from'] : null;
        $to = isset($filters['date_to']) ? (string) $filters['date_to'] : null;
        $perPage = (int) ($filters['per_page'] ?? Defaults::MOVEMENTS_PER_PAGE);

        $base = StockMovement::query()
            ->where('product_id', $productId)
            ->betweenDates($from, $to);

        $movements = (clone $base)
            ->with(['product', 'warehouse'])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        // The summary is aggregated by MySQL over the SAME date-filtered set.
        $inbound = MovementType::inboundValues();
        $outbound = MovementType::outboundValues();

        $summary = (clone $base)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN movement_type IN (?, ?) THEN quantity ELSE 0 END), 0) as total_in, '.
                'COALESCE(SUM(CASE WHEN movement_type IN (?, ?) THEN quantity ELSE 0 END), 0) as total_out, '.
                'COALESCE(SUM(CASE WHEN movement_type IN (?, ?) THEN quantity '.
                'WHEN movement_type IN (?, ?) THEN -quantity ELSE 0 END), 0) as net_movement',
                [
                    $inbound[0], $inbound[1],
                    $outbound[0], $outbound[1],
                    $inbound[0], $inbound[1],
                    $outbound[0], $outbound[1],
                ],
            )
            ->first();

        return [
            'movements' => $movements,
            'summary' => [
                'total_units_in' => (int) $summary->total_in,
                'total_units_out' => (int) $summary->total_out,
                'net_movement' => (int) $summary->net_movement,
            ],
        ];
    }
}
