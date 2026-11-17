<?php

namespace App\Http\Requests;

use App\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'movement_type' => ['required', 'string', Rule::enum(MovementType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reference_type' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:reference_id'],
            'reference_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'required_with:reference_type'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
