<?php

declare(strict_types=1);

namespace App\Support\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;

/**
 * Thin facade over brick/money that pins every amount to the configured base
 * currency and a consistent rounding mode.
 *
 * The legacy system added currency with `floatval($a) + floatval($b)` and
 * passed money around as strings, which drifts. Nothing in this codebase should
 * perform arithmetic on a money value outside this class.
 */
final class Money
{
    public static function currency(): string
    {
        return config('pos.currency', 'USD');
    }

    public static function zero(): BrickMoney
    {
        return BrickMoney::zero(self::currency());
    }

    /**
     * Build a money value from anything the database or a request may hand us.
     */
    public static function of(BrickMoney|string|int|float|null $amount): BrickMoney
    {
        if ($amount instanceof BrickMoney) {
            return $amount;
        }

        if ($amount === null || $amount === '') {
            return self::zero();
        }

        return BrickMoney::of(
            is_float($amount) ? self::fromFloat($amount) : (string) $amount,
            self::currency(),
            roundingMode: RoundingMode::HalfUp,
        );
    }

    /**
     * Floats only ever enter the system from JSON payloads. Convert through a
     * fixed-precision string so the binary representation cannot leak in.
     */
    private static function fromFloat(float $amount): string
    {
        return number_format($amount, config('pos.calculation_scale', 4), '.', '');
    }

    /**
     * Round a working value to the currency's minor unit, e.g. after applying
     * a percentage. This is the only place rounding is applied to a total.
     */
    public static function round(BrickMoney $money): BrickMoney
    {
        return $money->toContext($money->getContext(), RoundingMode::HalfUp);
    }

    /**
     * Apply a percentage, returning a value rounded to the minor unit.
     */
    public static function percentageOf(BrickMoney $money, string|float $percent): BrickMoney
    {
        $rate = is_float($percent)
            ? self::fromFloat($percent)
            : (string) $percent;

        // Multiply and divide in unrounded BigDecimal arithmetic, rounding
        // only once at the very end. Chaining BrickMoney::multipliedBy()
        // then ->dividedBy() instead forces the *product* back into the
        // money's fixed minor-unit scale before the division even happens --
        // for a percent value whose true result sits within a razor's edge
        // of the final rounding boundary, that premature rounding can flip
        // the last digit. Dividing to 10 extra digits of scale first (rather
        // than straight to the minor unit) pushes that edge case down by 10
        // orders of magnitude before the one real rounding step happens.
        $result = $money->getAmount()
            ->multipliedBy($rate)
            ->dividedBy('100', scale: $money->getAmount()->getScale() + 10, roundingMode: RoundingMode::HalfUp);

        return BrickMoney::of($result, $money->getCurrency(), roundingMode: RoundingMode::HalfUp);
    }

    /**
     * Round a total to the nearest cash denomination (Swedish rounding).
     * Returns the rounded total; the caller records the difference as a
     * `rounding_adjustment` so the receipt reconciles.
     */
    public static function roundToCashIncrement(BrickMoney $money, float $increment): BrickMoney
    {
        if ($increment <= 0) {
            return $money;
        }

        $incrementDecimal = BigDecimal::of(self::fromFloat($increment));

        // BrickMoney::dividedBy() always returns a Money in the *same*
        // 2-decimal context, so dividing 12.37 by 0.05 came back as 247.40
        // -- an exact round-trip that rounded nothing. Dropping to
        // BigDecimal and forcing the quotient to scale 0 makes it a whole
        // number of increments (247), which multiplying back by the
        // increment then actually rounds to the nearest 0.05.
        $units = $money->getAmount()->dividedBy($incrementDecimal, 0, RoundingMode::HalfUp);

        return BrickMoney::of($units->multipliedBy($incrementDecimal), $money->getCurrency(), roundingMode: RoundingMode::HalfUp);
    }

    public static function format(BrickMoney $money): string
    {
        return $money->formatToLocale(app()->getLocale() === 'en' ? 'en_US' : app()->getLocale());
    }
}
