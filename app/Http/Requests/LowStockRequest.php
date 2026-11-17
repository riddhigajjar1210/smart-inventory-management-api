<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LowStockRequest extends FormRequest
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
            'threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
