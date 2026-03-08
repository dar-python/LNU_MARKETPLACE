<?php

namespace App\Http\Requests;

use App\Models\PostReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public const MAX_EVIDENCE_FILE_SIZE_KB = 10240;

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
            'reason_category' => ['required', 'string', Rule::in(PostReport::REASON_CATEGORIES)],
            'description' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_EVIDENCE_FILE_SIZE_KB],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (! $this->has('reason_category') && $this->has('report_category')) {
            $this->merge([
                'reason_category' => $this->input('report_category'),
            ]);
        }

        foreach (['reason_category', 'description'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    $value = null;
                } elseif ($key === 'reason_category') {
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
