<?php

declare(strict_types=1);

namespace App\Support\Money\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A non-negative decimal amount, at most 4 decimal places, matching the
 * money-column scale (decimal(19,4)) and the regex discipline already
 * enforced in InventoryService::assertNumeric() for quantities.
 */
final class ValidDecimal implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('The :attribute must be a valid amount.');

            return;
        }

        if (! preg_match('/^\d{1,15}(\.\d{1,4})?$/', trim((string) $value))) {
            $fail('The :attribute must be a valid amount.');
        }
    }
}
