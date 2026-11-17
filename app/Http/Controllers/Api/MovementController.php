<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovementHistoryRequest;
use App\Http\Resources\MovementHistoryResource;
use App\Models\Product;
use App\Services\MovementHistoryService;

class MovementController extends Controller
{
    public function index(
        Product $product,
        MovementHistoryRequest $request,
        MovementHistoryService $service,
    ): MovementHistoryResource {
        return new MovementHistoryResource(
            $service->history($product->id, $request->validated()),
        );
    }
}
