<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

// Category hierarchy — cached, tree response.
Route::get('/categories/tree', [CategoryController::class, 'tree']);

// Product catalogue and per-product movement history.
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}/movements', [MovementController::class, 'index'])
    ->whereNumber('product');

// Stock writes and inventory reports.
Route::post('/stock/adjust', [StockController::class, 'adjust']);
Route::get('/inventory/summary', [InventoryController::class, 'summary']);
Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
