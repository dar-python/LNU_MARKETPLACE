<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'listing_id' => [
                'required',
                'integer',
                Rule::exists('listings', 'id')->where(static function (Builder $query): void {
                    $query->whereNull('deleted_at');
                }),
                Rule::unique('favorites', 'listing_id')->where(static function (Builder $query) use ($userId): void {
                    $query->where('user_id', $userId);
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'listing_id.exists' => 'The selected listing does not exist.',
            'listing_id.unique' => 'The listing has already been favorited.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('listing_id')) {
            return;
        }

        $value = $this->input('listing_id');

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                $value = null;
            }
        }

        $this->merge([
            'listing_id' => $value,
        ]);
    }
}
