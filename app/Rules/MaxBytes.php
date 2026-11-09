<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Limits a string by BYTES, not characters. bcrypt silently ignores everything
 * after 72 bytes, and a letter such as "ɛ" takes 2 bytes in UTF-8 — so Laravel's
 * max:72 (which counts characters) is not enough for passwords.
 */
class MaxBytes implements ValidationRule
{
    public function __construct(private int $maxBytes)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->maxBytes) {
            $fail("The :attribute is too long: at most {$this->maxBytes} characters, or fewer if it uses accented or special letters.");
        }
    }
}
