<?php

declare(strict_types=1);

namespace App\Domain\Taxation;

use App\Domain\Taxation\Data\AppliedTax;
use App\Domain\Taxation\Data\TaxableLine;
use App\Domain\Taxation\Data\TaxResult;
use App\Settings\TaxSettings;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Calculates tax for a set of lines.
 *
 * Pure by construction: it takes lines and a pre-resolved RateMatrix and
 * returns a value object. No Eloquent, no queries, no config reads beyond the
 * inclusive-pricing flag passed in. That is what makes it testable, and the
 * legacy Tax_lib (476 lines, no tests, database access inside the loop) is the
 * cautionary example.
 */
final class TaxEngine
{
    public function __construct(
        private readonly bool $pricesIncludeTax,
    ) {}

    public static function fromSettings(): self
    {
        return new self(app(TaxSettings::class)->prices_include_tax);
    }

    /**
     * Whether prices are entered tax-inclusive. Exposed so callers that need
     * to assemble totals consistently with the tax calculation (CartPricer)
     * read the same flag this engine was constructed with, rather than a
     * second, independently-sourced copy of it.
     */
    public function pricesIncludeTax(): bool
    {
        return $this->pricesIncludeTax;
    }

    /**
     * @param  list<TaxableLine>  $lines
     */
    public function calculate(array $lines, RateMatrix $matrix): TaxResult
    {
        $lineTaxes = [];
        $summaryBuckets = [];
        $totalTax = MoneySupport::zero();

        foreach ($lines as $line) {
            if ($line->isExempt || $line->netAmount->isZero()) {
                continue;
            }

            $rates = $matrix->forCategory($line->taxCategoryId);

            foreach ($rates as $rate) {
                $taxAmount = $this->taxFor($line->netAmount, (string) $rate->rate);

                if ($taxAmount->isZero()) {
                    continue;
                }

                $taxableAmount = $this->pricesIncludeTax
                    ? $line->netAmount->minus($taxAmount)
                    : $line->netAmount;

                $applied = new AppliedTax(
                    taxRateId: $rate->id,
                    name: $rate->name,
                    rate: (string) $rate->rate,
                    taxableAmount: $taxableAmount,
                    taxAmount: $taxAmount,
                    isInclusive: $this->pricesIncludeTax,
                    printSequence: (int) $rate->cascade_sequence,
                );

                $lineTaxes[$line->lineNumber][] = $applied;
                $totalTax = $totalTax->plus($taxAmount);

                // Accumulate the per-rate summary the receipt prints.
                $key = (string) $rate->id;

                if (! isset($summaryBuckets[$key])) {
                    $summaryBuckets[$key] = $applied;

                    continue;
                }

                $existing = $summaryBuckets[$key];
                $summaryBuckets[$key] = new AppliedTax(
                    taxRateId: $existing->taxRateId,
                    name: $existing->name,
                    rate: $existing->rate,
                    taxableAmount: $existing->taxableAmount->plus($taxableAmount),
                    taxAmount: $existing->taxAmount->plus($taxAmount),
                    isInclusive: $existing->isInclusive,
                    printSequence: $existing->printSequence,
                );
            }
        }

        $summary = array_values($summaryBuckets);
        usort($summary, fn (AppliedTax $a, AppliedTax $b) => $a->printSequence <=> $b->printSequence);

        return new TaxResult($lineTaxes, $summary, $totalTax);
    }

    /**
     * Tax on an amount.
     *
     * Exclusive: amount * rate / 100
     * Inclusive: amount * rate / (100 + rate)  -- the tax already inside the price
     */
    public function taxFor(Money $amount, string $rate): Money
    {
        if (bccomp($rate, '0', 6) === 0) {
            return MoneySupport::zero();
        }

        $divisor = $this->pricesIncludeTax
            ? bcadd('100', $rate, 6)
            : '100';

        // Multiply and divide in unrounded BigDecimal arithmetic, rounding
        // only once at the very end -- see Money::percentageOf() for why
        // chaining BrickMoney::multipliedBy()->dividedBy() instead (which
        // rounds the product into the amount's fixed minor-unit scale before
        // the division happens) risks a one-minor-unit error for a rate
        // whose true result sits within a razor's edge of the rounding
        // boundary.
        $result = $amount->getAmount()
            ->multipliedBy($rate)
            ->dividedBy($divisor, scale: $amount->getAmount()->getScale() + 10, roundingMode: RoundingMode::HalfUp);

        return Money::of($result, $amount->getCurrency(), roundingMode: RoundingMode::HalfUp);
    }
}
