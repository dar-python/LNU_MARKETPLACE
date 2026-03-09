<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class AllowedLnuEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $allowedDomains = config('lnu.allowed_email_domains', []);
        $normalizedDomains = array_values(array_filter(array_map(
            static fn ($domain): string => Str::lower(trim((string) $domain)),
            is_array($allowedDomains) ? $allowedDomains : []
        )));

        $domain = Str::lower(Str::after(trim($value), '@'));

        if ($normalizedDomains === [] || ! in_array($domain, $normalizedDomains, true)) {
            $fail('The email domain is not allowed.');
        }
    }
}
