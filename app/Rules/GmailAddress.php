<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Every account uses a Gmail address (name@gmail.com): sign-in, sign-up,
 * and wherever an administrator, teacher or the user sets an email.
 */
class GmailAddress implements ValidationRule
{
    const MESSAGE = 'Only Gmail addresses (ending in @gmail.com) are allowed.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function passes(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[^@\s]+@gmail\.com$/i', trim($value)) === 1;
    }
}
