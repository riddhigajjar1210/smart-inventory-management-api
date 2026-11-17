<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();

            // Self reference builds the arbitrary-depth tree. NULL means a root node.
            // nullOnDelete keeps children (they get re-parented by the tree service).
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');

            // Unique so lookups/slug links are O(1) and cannot duplicate.
            $table->string('slug')->unique();

            // Drives the "exclude inactive categories" tree rule.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The tree query always asks for active nodes first.
            $table->index('is_active', 'categories_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
