<?php

declare(strict_types=1);

namespace App\Support\Printing\Rules;

use App\Support\Printing\WindowsPrinterTarget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Windows print queue: a share on the server ("XP80"), a share on another
 * PC (\\REGISTER-2\XP80 or smb://REGISTER-2/XP80), or LPT/COM port.
 */
final class ValidWindowsPrinterTarget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (str_contains($value, '@')) {
            $fail('The :attribute must not contain a username or password.');

            return;
        }

        if (! WindowsPrinterTarget::isValid($value)) {
            $fail('The :attribute must be a share name (XP80) or a network path (\\\\REGISTER-2\\XP80).');
        }
    }
}
