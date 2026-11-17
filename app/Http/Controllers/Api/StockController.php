<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Resources\StockResource;
use App\Services\StockAdjustmentService;

class StockController extends Controller
{
    public function adjust(AdjustStockRequest $request, StockAdjustmentService $service): StockResource
    {
        return new StockResource($service->adjust($request->validated()));
    }
}
