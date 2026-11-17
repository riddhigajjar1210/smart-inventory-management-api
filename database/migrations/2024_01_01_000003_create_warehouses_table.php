<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Warehouse filters always restrict to active locations.
            $table->index('is_active', 'warehouses_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
