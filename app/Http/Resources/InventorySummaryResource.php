<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventorySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this['product_id'],
            'name' => $this['name'],
            'sku' => $this['sku'],
            'total_quantity' => $this['total_quantity'],
            'total_reserved' => $this['total_reserved'],
            'available_quantity' => $this['available_quantity'],
            'is_zero_stock' => $this['is_zero_stock'],
            'highest_stock_warehouse' => $this['highest_stock_warehouse'],
        ];
    }
}
