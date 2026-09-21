<?php

declare(strict_types=1);

namespace App\Support\Money\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A non-negative decimal amount, at most 2 decimal places -- the currency's
 * actual minor-unit precision that every value passes through on its way to
 * and from `Money::of()`/`MoneyCast` (both fixed to the currency's default
 * 2-decimal context, regardless of the underlying decimal(19,4) column).
 *
 * Use this instead of ValidDecimal for any field cast with MoneyCast
 * (cost_price, unit_price, ...): ValidDecimal's 4-decimal-place allowance
 * matches the column's raw scale, but MoneyCast silently rounds anything
 * past 2 decimals away on save, so accepting "0.0850" there does not mean
 * what it looks like it means -- it becomes "0.09" (or "0.08") the moment
 * it round-trips through the model, with no indication to the user that
 * their input was not honoured. Rejecting it here instead is honest about
 * what the system can actually store.
 */
final class ValidMoneyAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('The :attribute must be a valid amount.');

            return;
        }

        if (! preg_match('/^\d{1,15}(\.\d{1,2})?$/', trim((string) $value))) {
            $fail('The :attribute must be a valid amount with at most 2 decimal places.');
        }
    }
}
