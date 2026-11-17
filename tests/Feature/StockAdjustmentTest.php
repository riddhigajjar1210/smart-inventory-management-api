<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Cache\CacheKeys;
use App\Support\Defaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::query()->create(['name' => 'Goods', 'slug' => 'goods', 'is_active' => true]);
        $this->warehouse = Warehouse::query()->create(['name' => 'Main', 'city' => 'Pune', 'is_active' => true]);
        $this->product = Product::query()->create([
            'name' => 'Widget', 'sku' => 'W-1', 'description' => 'Test',
            'category_id' => $category->id, 'base_price' => 100, 'is_active' => true,
        ]);

        Stock::query()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
            'reserved_quantity' => 5,
        ]);
    }

    public function test_stock_in_returns_updated_available_quantity_and_logs_movement(): void
    {
        $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'stock_in',
            'quantity' => 10,
        ])->assertOk()->assertJsonPath('data.available_quantity', 25);

        $this->assertSame(30, (int) Stock::query()->first()->quantity);
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_stock_out_below_zero_is_rejected(): void
    {
        $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'stock_out',
            'quantity' => 20,
        ])->assertStatus(422)->assertJsonStructure(['message', 'errors']);

        $this->assertSame(20, (int) Stock::query()->first()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_reservation_and_release_keep_availability_correct(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
        ];

        $this->postJson('/api/stock/adjust', $payload + ['movement_type' => 'reservation'])
            ->assertOk()
            ->assertJsonPath('data.available_quantity', 5)
            ->assertJsonPath('data.reserved_quantity', 15);

        $this->postJson('/api/stock/adjust', $payload + ['movement_type' => 'reservation_release'])
            ->assertOk()
            ->assertJsonPath('data.available_quantity', 15)
            ->assertJsonPath('data.reserved_quantity', 5);
    }

    public function test_adjustment_invalidates_summary_cache(): void
    {
        Cache::put(CacheKeys::inventorySummary(), [['stale' => true]], 300);

        $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'stock_in',
            'quantity' => 1,
        ])->assertOk();

        $this->assertNull(Cache::get(CacheKeys::inventorySummary()));

        $this->getJson('/api/inventory/summary')
            ->assertOk()
            ->assertJsonPath('data.0.available_quantity', 16);
    }

    public function test_adjustment_invalidates_cached_product_list(): void
    {
        $this->getJson('/api/products?search=Widget')
            ->assertOk()
            ->assertJsonPath('data.0.available_total', 15);

        $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'stock_in',
            'quantity' => 10,
        ])->assertOk();

        $this->getJson('/api/products?search=Widget')
            ->assertOk()
            ->assertJsonPath('data.0.available_total', 25);
    }

    public function test_adjustment_bumps_low_stock_cache_version(): void
    {
        $this->getJson('/api/inventory/low-stock?threshold=20')->assertOk();

        $oldKey = CacheKeys::lowStock(20, Defaults::LOW_STOCK_PER_PAGE);
        $oldVersion = CacheKeys::lowStockVersion();

        $this->assertNotNull(Cache::get($oldKey));

        $this->postJson('/api/stock/adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => 'stock_in',
            'quantity' => 20,
        ])->assertOk();

        $this->assertSame($oldVersion + 1, CacheKeys::lowStockVersion());
        $this->assertNotSame($oldKey, CacheKeys::lowStock(20, Defaults::LOW_STOCK_PER_PAGE));

        // 15 + 20 = 35 available, so the product is no longer below the threshold.
        $this->getJson('/api/inventory/low-stock?threshold=20')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
