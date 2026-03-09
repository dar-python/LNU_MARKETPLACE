<?php

namespace App\Http\Requests;

use App\Rules\AllowedLnuEmail;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['nullable', 'string', 'email', 'max:255', new AllowedLnuEmail, 'required_without:student_id'],
            'student_id' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->normalizeString($this->input('email'));
        $studentId = $this->normalizeString($this->input('student_id'));
        $identifier = $this->normalizeString($this->input('identifier'));

        if ($email === null && $studentId === null && $identifier !== null) {
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $email = $identifier;
            } else {
                $studentId = $identifier;
            }
        }

        $this->merge([
            'email' => $email,
            'student_id' => $studentId,
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
