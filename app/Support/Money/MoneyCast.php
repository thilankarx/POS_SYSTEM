<?php

declare(strict_types=1);

namespace App\Support\Money;

use Brick\Money\Money as BrickMoney;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a decimal(19,4) column to a Brick\Money value object and back.
 *
 * @implements CastsAttributes<BrickMoney, BrickMoney|string|int|float|null>
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BrickMoney
    {
        return $value === null ? null : Money::of($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) Money::of($value)->getAmount();
    }
}
