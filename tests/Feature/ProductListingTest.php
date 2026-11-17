<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    private Category $parent;
    private Category $child;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private Product $chair;
    private Product $desk;
    private Product $lamp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = Category::query()->create([
            'name' => 'Furniture', 'slug' => 'furniture', 'is_active' => true,
        ]);
        $this->child = Category::query()->create([
            'name' => 'Chairs', 'slug' => 'chairs', 'parent_id' => $this->parent->id, 'is_active' => true,
        ]);
        $this->warehouseA = Warehouse::query()->create(['name' => 'Depot A', 'city' => 'Pune', 'is_active' => true]);
        $this->warehouseB = Warehouse::query()->create(['name' => 'Depot B', 'city' => 'Mumbai', 'is_active' => true]);

        $this->chair = $this->product('Ergonomic Chair', 'SKU-CHAIR', $this->child->id, 100);
        $this->desk = $this->product('Standing Desk', 'SKU-DESK', $this->parent->id, 500);
        $this->lamp = $this->product('Desk Lamp', 'SKU-LAMP', $this->parent->id, 50);

        Stock::query()->create([
            'product_id' => $this->chair->id, 'warehouse_id' => $this->warehouseA->id,
            'quantity' => 50, 'reserved_quantity' => 10,
        ]);
        Stock::query()->create([
            'product_id' => $this->desk->id, 'warehouse_id' => $this->warehouseB->id,
            'quantity' => 10, 'reserved_quantity' => 10,
        ]);
        Stock::query()->create([
            'product_id' => $this->lamp->id, 'warehouse_id' => $this->warehouseA->id,
            'quantity' => 200, 'reserved_quantity' => 0,
        ]);
    }

    public function test_category_filter_includes_all_descendant_categories(): void
    {
        $this->getJson('/api/products?category='.$this->parent->id)
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_warehouse_and_availability_filters_combine(): void
    {
        $this->getJson('/api/products?warehouse='.$this->warehouseA->id.'&available=true')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['sku' => 'SKU-CHAIR'])
            ->assertJsonFragment(['sku' => 'SKU-LAMP'])
            ->assertJsonMissing(['sku' => 'SKU-DESK']);
    }

    public function test_price_range_and_search_combine(): void
    {
        $this->getJson('/api/products?min_price=200&max_price=600&search=Standing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['sku' => 'SKU-DESK']);
    }

    public function test_product_list_cache_is_scoped_by_filter_combination(): void
    {
        $chair = $this->getJson('/api/products?search=Chair')->assertOk()->json('data.0.sku');
        $lamp = $this->getJson('/api/products?search=Lamp')->assertOk()->json('data.0.sku');

        $this->assertSame('SKU-CHAIR', $chair);
        $this->assertSame('SKU-LAMP', $lamp);
    }

    public function test_sort_by_available_stock_uses_database_aggregation(): void
    {
        $response = $this->getJson('/api/products?sort=available&direction=desc')
            ->assertOk();

        $this->assertSame('SKU-LAMP', $response->json('data.0.sku'));
        $this->assertSame('SKU-CHAIR', $response->json('data.1.sku'));
    }

    private function product(string $name, string $sku, int $categoryId, int $price): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'description' => 'Test product',
            'category_id' => $categoryId,
            'base_price' => $price,
            'is_active' => true,
        ]);
    }
}
