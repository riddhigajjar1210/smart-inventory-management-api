<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            // Values come from MovementType enum; no magic strings in application code.
            $table->string('movement_type', 32);

            $table->unsignedBigInteger('quantity');

            // Polymorphic source document (purchase order, reservation, etc.).
            $table->nullableMorphs('reference');

            $table->text('note')->nullable();

            // Business event time used by the date filters.
            $table->timestamp('moved_at')->useCurrent();

            // Audit column only. Deliberately no updated_at: this table is an immutable log.
            $table->timestamp('created_at')->useCurrent();

            // Movement history is always filtered by product and ordered by moved_at.
            $table->index(['product_id', 'moved_at'], 'movements_product_moved_at_index');

            // Some reports filter by warehouse first.
            $table->index(['warehouse_id', 'moved_at'], 'movements_warehouse_moved_at_index');

            // Summary aggregation groups by movement type.
            $table->index('movement_type', 'movements_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
