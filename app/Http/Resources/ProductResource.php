<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),
            'total_quantity' => (int) ($this->stocks_sum_quantity ?? 0),
            'total_reserved' => (int) ($this->stocks_sum_reserved_quantity ?? 0),
            'available_total' => (int) ($this->stocks_sum_available_quantity ?? 0),
        ];
    }
}
