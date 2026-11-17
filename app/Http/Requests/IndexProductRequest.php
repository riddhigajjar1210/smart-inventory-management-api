<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept `true`/`false` as well as 1/0 for the availability flag.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('available')) {
            $this->merge([
                'available' => filter_var($this->input('available'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'integer', 'exists:categories,id'],
            'warehouse' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'available' => ['sometimes', 'boolean'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gte:min_price'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in(['name', 'price', 'available'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
