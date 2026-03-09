<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeclineListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'moderation_note' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('moderation_note')) {
            return;
        }

        $moderationNote = $this->input('moderation_note');

        if (! is_string($moderationNote)) {
            return;
        }

        $moderationNote = trim($moderationNote);

        $this->merge([
            'moderation_note' => $moderationNote === '' ? null : $moderationNote,
        ]);
    }
}
