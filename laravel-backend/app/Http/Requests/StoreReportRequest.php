<?php

namespace App\Http\Requests;

use App\Models\ModerationReport;
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
            'report_category' => ['required', 'string', Rule::in(ModerationReport::REPORT_CATEGORIES)],
            'description' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_EVIDENCE_FILE_SIZE_KB],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['report_category', 'description'] as $key) {
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
