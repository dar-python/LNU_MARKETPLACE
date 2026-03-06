<?php

namespace App\Http\Requests;

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
            'identifier'             => ['required', 'string', 'max:255'],
            'otp'                    => ['required', 'string', 'digits:6'],
            'password'               => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation'  => ['required', 'string'],
        ];
    }
}