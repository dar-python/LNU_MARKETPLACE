<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DecideInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = $this->user()?->id;
        $inquiry = $this->route('inquiry');

        return is_int($userId)
            && $inquiry instanceof Inquiry
            && $inquiry->canBeDecidedBy($userId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Inquiry::DECISION_STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            return;
        }

        $status = $this->input('status');

        if (is_string($status)) {
            $status = strtolower(trim($status));
        }

        $this->merge([
            'status' => $status,
        ]);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error('Forbidden.', null, 403)
        );
    }
}
