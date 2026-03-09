<?php

namespace App\Http\Requests;

use App\Rules\AllowedLnuEmail;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email'                  => ['required', 'string', 'email', 'max:255', new AllowedLnuEmail],
            'otp'                    => ['required', 'string', 'digits:6'],
            'password'               => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation'  => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->normalizeString($this->input('email'));
        $identifier = $this->normalizeString($this->input('identifier'));

        if ($email === null && $identifier !== null) {
            $email = $identifier;
        }

        $this->merge([
            'email' => $email,
        ]);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
