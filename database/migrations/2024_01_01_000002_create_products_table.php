<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();

            // Every product belongs to a category; deleting a category removes its products.
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Business identifier: uniqueness is enforced in the database, not in PHP.
            $table->string('sku')->unique();

            $table->text('description')->nullable();

            // decimal, never float, for money.
            $table->decimal('base_price', 12, 2);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Product listing filters by category + active state together.
            $table->index(['category_id', 'is_active'], 'products_category_active_index');

            // Price-range filter and price sorting.
            $table->index('base_price', 'products_base_price_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
