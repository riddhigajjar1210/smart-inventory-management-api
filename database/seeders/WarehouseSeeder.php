<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Central Depot', 'city' => 'Mumbai'],
            ['name' => 'West Hub', 'city' => 'Pune'],
            ['name' => 'East Hub', 'city' => 'Kolkata'],
        ] as $warehouse) {
            Warehouse::query()->updateOrCreate(
                ['name' => $warehouse['name']],
                ['city' => $warehouse['city'], 'is_active' => true],
            );
        }
    }
}
