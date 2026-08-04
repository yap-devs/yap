<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $separator_position = strrpos($value, '@');

        if ($separator_position === false) {
            return;
        }

        $local_part = substr($value, 0, $separator_position);

        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._%+\-]{0,63}\z/D', $local_part) !== 1) {
            $fail('validation.email')->translate();
        }
    }
}
