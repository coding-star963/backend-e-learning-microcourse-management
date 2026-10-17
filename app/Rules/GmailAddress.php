<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Student accounts must use a Gmail address (name@gmail.com).
 */
class GmailAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[^@\s]+@gmail\.com$/i', trim($value))) {
            $fail('Student accounts must use a Gmail address (ending in @gmail.com).');
        }
    }
}
