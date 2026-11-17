<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductFilterService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request, ProductFilterService $service): AnonymousResourceCollection
    {
        return ProductResource::collection($service->list($request->validated()));
    }
}
