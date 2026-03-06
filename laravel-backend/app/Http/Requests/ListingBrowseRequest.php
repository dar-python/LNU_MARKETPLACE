<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingBrowseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'condition' => ['nullable', 'string', Rule::in(['brandnew', 'preowned'])],
            'status' => ['nullable', 'string', Rule::in(['available', 'reserved', 'sold'])],
            'sort_by' => ['nullable', 'string', Rule::in(['newest', 'oldest', 'price_asc', 'price_desc', 'title_asc', 'title_desc'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keys = [
            'q',
            'category_id',
            'min_price',
            'max_price',
            'condition',
            'status',
            'sort_by',
            'sort_dir',
            'page',
            'per_page',
        ];

        $normalized = [];
        foreach ($keys as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }

            $normalized[$key] = $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
