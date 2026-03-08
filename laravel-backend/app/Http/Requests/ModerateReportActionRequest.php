<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModerateReportActionRequest extends FormRequest
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
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('admin_note')) {
            return;
        }

        $adminNote = $this->input('admin_note');

        if (! is_string($adminNote)) {
            return;
        }

        $adminNote = trim($adminNote);

        $this->merge([
            'admin_note' => $adminNote === '' ? null : $adminNote,
        ]);
    }
}
