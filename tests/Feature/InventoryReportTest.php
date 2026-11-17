<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    private Product $withStock;
    private Product $zeroStock;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::query()->create(['name' => 'Goods', 'slug' => 'goods', 'is_active' => true]);
        $this->warehouseA = Warehouse::query()->create(['name' => 'A', 'city' => 'Pune', 'is_active' => true]);
        $this->warehouseB = Warehouse::query()->create(['name' => 'B', 'city' => 'Mumbai', 'is_active' => true]);

        $this->withStock = Product::query()->create([
            'name' => 'Stocked', 'sku' => 'S-1', 'description' => 'Test',
            'category_id' => $category->id, 'base_price' => 10, 'is_active' => true,
        ]);
        $this->zeroStock = Product::query()->create([
            'name' => 'Empty', 'sku' => 'S-2', 'description' => 'Test',
            'category_id' => $category->id, 'base_price' => 10, 'is_active' => true,
        ]);

        Stock::query()->create([
            'product_id' => $this->withStock->id, 'warehouse_id' => $this->warehouseA->id,
            'quantity' => 30, 'reserved_quantity' => 5,
        ]);
        Stock::query()->create([
            'product_id' => $this->withStock->id, 'warehouse_id' => $this->warehouseB->id,
            'quantity' => 50, 'reserved_quantity' => 0,
        ]);
        Stock::query()->create([
            'product_id' => $this->zeroStock->id, 'warehouse_id' => $this->warehouseA->id,
            'quantity' => 0, 'reserved_quantity' => 0,
        ]);

        foreach ([
            [MovementType::StockIn, 100, now()->subDays(2)],
            [MovementType::StockOut, 30, now()->subDays(1)],
            [MovementType::Reservation, 10, now()->subDays(40)],
            [MovementType::ReservationRelease, 10, now()->subDays(40)],
        ] as [$type, $quantity, $date]) {
            StockMovement::query()->create([
                'product_id' => $this->withStock->id,
                'warehouse_id' => $this->warehouseA->id,
                'movement_type' => $type->value,
                'quantity' => $quantity,
                'moved_at' => $date,
            ]);
        }
    }

    public function test_summary_reports_totals_zero_stock_flag_and_top_warehouse(): void
    {
        $response = $this->getJson('/api/inventory/summary')->assertOk();

        $stocked = collect($response->json('data'))->firstWhere('sku', 'S-1');
        $empty = collect($response->json('data'))->firstWhere('sku', 'S-2');

        $this->assertSame(80, $stocked['total_quantity']);
        $this->assertSame(5, $stocked['total_reserved']);
        $this->assertSame(75, $stocked['available_quantity']);
        $this->assertFalse($stocked['is_zero_stock']);
        $this->assertSame('B', $stocked['highest_stock_warehouse']['name']);
        $this->assertTrue($empty['is_zero_stock']);
    }

    public function test_low_stock_is_threshold_scoped_and_ordered(): void
    {
        $this->getJson('/api/inventory/low-stock?threshold=1')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'S-2')
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/inventory/low-stock?threshold=100')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_movement_history_paginates_and_aggregates_summary(): void
    {
        $response = $this->getJson('/api/products/'.$this->withStock->id.'/movements?per_page=2')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(4, $response->json('meta.total'));
        $this->assertSame(110, $response->json('summary.total_units_in'));
        $this->assertSame(40, $response->json('summary.total_units_out'));
        $this->assertSame(70, $response->json('summary.net_movement'));

        $filtered = $this->getJson('/api/products/'.$this->withStock->id.'/movements?date_from='
            .now()->subDays(3)->toDateString().'&date_to='.now()->toDateString())
            ->assertOk();

        $this->assertSame(2, $filtered->json('meta.total'));
        $this->assertSame(70, $filtered->json('summary.net_movement'));
    }
}
