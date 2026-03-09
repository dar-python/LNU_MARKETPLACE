<?php

namespace App\Http\Requests;

use App\Models\PostReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportDashboardRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(['listing', 'user'])],
            'status' => ['nullable', 'string', Rule::in(PostReport::STATUSES)],
            'reason_category' => ['nullable', 'string', Rule::in(PostReport::REASON_CATEGORIES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keys = [
            'type',
            'status',
            'reason_category',
            'date_from',
            'date_to',
            'search',
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
                } elseif (in_array($key, ['type', 'status', 'reason_category', 'sort_by', 'sort_dir'], true)) {
                    $value = strtolower($value);
                }
            }

            $normalized[$key] = $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
