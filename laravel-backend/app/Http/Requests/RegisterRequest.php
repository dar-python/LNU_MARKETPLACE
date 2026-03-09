<?php

namespace App\Http\Requests;

use App\Rules\AllowedLnuEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
        $passwordRule = Password::min(8)->mixedCase()->numbers()->symbols();
        $studentIdRegex = $this->studentIdRegex();

        if ((bool) config('lnu.password_uncompromised', false)) {
            $passwordRule = $passwordRule->uncompromised();
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'student_id' => [
                'required',
                'string',
                "regex:{$studentIdRegex}",
                Rule::unique('users', 'student_id'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                new AllowedLnuEmail,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    if ((bool) config('lnu.enforce_email_student_id_match', true)) {
                        $studentId = (string) $this->input('student_id');
                        $emailLocalPart = Str::before($value, '@');

                        if ($emailLocalPart === '' || $emailLocalPart !== $studentId) {
                            $fail('Email must match Student ID.');
                        }
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                $passwordRule,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $password = Str::lower((string) $value);
                    $studentId = Str::lower((string) $this->input('student_id'));
                    $email = (string) $this->input('email');
                    $emailLocalPart = Str::lower(Str::before($email, '@'));
                    $name = Str::lower(preg_replace('/\s+/', '', (string) $this->input('name')) ?? '');

                    if ($studentId !== '' && Str::contains($password, $studentId)) {
                        $fail('The password must not contain your student ID.');
                    }

                    if ($emailLocalPart !== '' && Str::contains($password, $emailLocalPart)) {
                        $fail('The password must not contain your email username.');
                    }

                    if ($name !== '' && Str::contains($password, $name)) {
                        $fail('The password must not contain your name.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.regex' => 'Student ID must be valid.',
        ];
    }

    private function studentIdRegex(): string
    {
        $allowedPrefixes = config('lnu.allowed_student_id_prefixes', []);
        $normalizedPrefixes = array_values(array_filter(array_map(
            static fn ($prefix): string => trim((string) $prefix),
            is_array($allowedPrefixes) ? $allowedPrefixes : []
        )));

        if ($normalizedPrefixes === []) {
            $normalizedPrefixes = ['210', '220', '230', '240', '250', '260', '270', '280'];
        }

        $alternation = implode('|', array_map(
            static fn (string $prefix): string => preg_quote($prefix, '/'),
            $normalizedPrefixes
        ));

        return "/^({$alternation})\\d{4}$/";
    }
}
