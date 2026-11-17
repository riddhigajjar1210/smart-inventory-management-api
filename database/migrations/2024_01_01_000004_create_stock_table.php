<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table): void {
            $table->id();

            // One row per product/warehouse pair is the invariant the locking relies on.
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('quantity')->default(0);
            $table->unsignedBigInteger('reserved_quantity')->default(0);

            // Generated in MySQL: availability can never drift from its two source columns.
            // It also lets the availability filter and low-stock sort use a real index.
            $table->unsignedBigInteger('available_quantity')
                ->storedAs('quantity - reserved_quantity');

            $table->timestamps();

            // The composite unique key is what makes concurrent upserts safe at the DB level.
            $table->unique(['product_id', 'warehouse_id'], 'stock_product_warehouse_unique');

            // Low-stock and "sort by available stock" queries filter/sort on this column.
            $table->index('available_quantity', 'stock_available_quantity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
