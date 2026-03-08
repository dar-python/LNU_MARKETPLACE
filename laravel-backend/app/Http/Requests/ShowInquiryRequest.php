<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ShowInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inquiry = $this->route('inquiry');
        $userId = (int) ($this->user()?->id ?? 0);

        if (! $inquiry instanceof Inquiry || $userId <= 0) {
            return false;
        }

        if (
            $userId === (int) $inquiry->sender_user_id
            || $userId === (int) $inquiry->recipient_user_id
        ) {
            return true;
        }

        return $inquiry->listing()->where('user_id', $userId)->exists();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error('Forbidden.', null, 403)
        );
    }
}
