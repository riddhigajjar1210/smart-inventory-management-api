<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $electronics = $this->category('Electronics', 'electronics');
        $laptops = $this->category('Laptops', 'laptops', $electronics->id);
        $this->category('Gaming Laptops', 'gaming-laptops', $laptops->id);
        $this->category('Audio', 'audio', $electronics->id);

        $furniture = $this->category('Furniture', 'furniture');
        $this->category('Chairs', 'chairs', $furniture->id);
        $this->category('Desks', 'desks', $furniture->id);

        $office = $this->category('Office Supplies', 'office-supplies');
        $this->category('Paper', 'paper', $office->id);

        // Inactive parent with an ACTIVE child: the tree must promote the child
        // to the nearest active ancestor (Office Supplies).
        $inactive = $this->category('Discontinued Range', 'discontinued-range', $office->id, false);
        $this->category('Notebooks', 'notebooks', $inactive->id);

        // Deliberately has no products assigned.
        $this->category('Clearance', 'clearance');
    }

    private function category(
        string $name,
        string $slug,
        ?int $parentId = null,
        bool $isActive = true,
    ): Category {
        return Category::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'parent_id' => $parentId,
                'is_active' => $isActive,
            ],
        );
    }
}
