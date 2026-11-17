<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryTreeResource;
use App\Services\CategoryTreeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function tree(CategoryTreeService $service): AnonymousResourceCollection
    {
        return CategoryTreeResource::collection($service->tree());
    }
}
