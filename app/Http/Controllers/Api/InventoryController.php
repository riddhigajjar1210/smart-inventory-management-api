<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LowStockRequest;
use App\Http\Resources\InventorySummaryResource;
use App\Http\Resources\LowStockResource;
use App\Services\InventorySummaryService;
use App\Services\LowStockService;
use App\Support\Defaults;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function summary(InventorySummaryService $service): AnonymousResourceCollection
    {
        return InventorySummaryResource::collection($service->summary());
    }

    public function lowStock(
        LowStockRequest $request,
        LowStockService $service,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        return LowStockResource::collection($service->lowStock(
            (int) ($validated['threshold'] ?? Defaults::LOW_STOCK_THRESHOLD),
            (int) ($validated['per_page'] ?? Defaults::LOW_STOCK_PER_PAGE),
        ));
    }
}
