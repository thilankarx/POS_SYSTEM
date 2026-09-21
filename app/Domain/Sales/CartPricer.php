<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Promotions\PromotionEngine;
use App\Domain\Promotions\PromotionSelector;
use App\Domain\Sales\Data\CartTotals;
use App\Domain\Sales\Data\PricedLine;
use App\Domain\Sales\Models\Cart;
use App\Domain\Taxation\Data\TaxableLine;
use App\Domain\Taxation\RateMatrix;
use App\Domain\Taxation\TaxEngine;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Turns a cart into money: line discounts, promotions, tax, rounding, total.
 *
 * Deliberately separate from the checkout action so the register can price a
 * cart on every change without any risk of side effects, and so pricing can be
 * tested without touching sales, stock or payments.
 */
final class CartPricer
{
    public function __construct(
        private readonly TaxEngine $taxEngine,
        private readonly PromotionSelector $promotionSelector,
        private readonly PromotionEngine $promotionEngine,
    ) {}

    public function price(Cart $cart, ?RateMatrix $matrix = null): CartTotals
    {
        $cart->loadMissing(['lines.item', 'customer']);

        $matrix ??= RateMatrix::forDate(now());
        $customerIsExempt = (bool) ($cart->customer?->is_tax_exempt ?? false);

        $working = [];

        foreach ($cart->lines as $line) {
            $quantity = (string) $line->quantity;
            $unitPrice = MoneySupport::of($line->unit_price);

            $gross = $unitPrice->multipliedBy($quantity, RoundingMode::HalfUp);
            $manualDiscount = $this->discountFor($gross, (string) $line->discount_value, (string) $line->discount_type);
            $net = $gross->minus($manualDiscount);

            $cost = MoneySupport::of($line->cost_price)->multipliedBy($quantity, RoundingMode::HalfUp);

            $working[$line->line_number] = [
                'line' => $line,
                'gross' => $gross,
                'manualDiscount' => $manualDiscount,
                'net' => $net,
                'cost' => $cost,
            ];
        }

        $candidates = $this->promotionSelector->candidatesFor($cart, $cart->coupon_code, now());
        $evaluation = $this->promotionEngine->evaluate($candidates, $cart, $working, $cart->coupon_code);

        foreach ($evaluation->perLineDiscount as $lineNumber => $promotionDiscount) {
            $working[$lineNumber]['promotionDiscount'] = $promotionDiscount;
            $working[$lineNumber]['appliedPromotionIds'] = $evaluation->promotionIdsByLine[$lineNumber] ?? [];
            $working[$lineNumber]['net'] = $working[$lineNumber]['net']->minus($promotionDiscount);
        }

        $taxableLines = [];

        foreach ($working as $lineNumber => $parts) {
            $taxableLines[] = new TaxableLine(
                lineNumber: (int) $lineNumber,
                taxCategoryId: $parts['line']->item?->tax_category_id,
                netAmount: $parts['net'],
                isExempt: $customerIsExempt,
            );
        }

        $taxResult = $this->taxEngine->calculate($taxableLines, $matrix);

        $subtotal = MoneySupport::zero();
        $discountTotal = MoneySupport::zero();
        $costTotal = MoneySupport::zero();
        $pricedLines = [];

        foreach ($working as $lineNumber => $parts) {
            $lineTax = $taxResult->taxForLine($lineNumber);

            // With tax-inclusive pricing the tax already sits inside `net`, so
            // it must not be added again when forming the line total.
            $lineTotal = $this->taxEngine->pricesIncludeTax()
                ? $parts['net']
                : $parts['net']->plus($lineTax);

            $discount = $parts['manualDiscount']->plus($parts['promotionDiscount']);

            $pricedLines[] = new PricedLine(
                lineNumber: $lineNumber,
                itemId: (int) $parts['line']->item_id,
                gross: $parts['gross'],
                discount: $discount,
                net: $parts['net'],
                tax: $lineTax,
                total: $lineTotal,
                cost: $parts['cost'],
                promotionDiscount: $parts['promotionDiscount'],
                appliedPromotionIds: $parts['appliedPromotionIds'],
            );

            $subtotal = $subtotal->plus($this->taxEngine->pricesIncludeTax() ? $parts['net']->minus($lineTax) : $parts['net']);
            $discountTotal = $discountTotal->plus($discount);
            $costTotal = $costTotal->plus($parts['cost']);
        }

        $total = $subtotal->plus($taxResult->totalTax);

        // Swedish rounding, when the smallest coin is larger than the minor unit.
        $increment = (float) config('pos.cash.rounding_increment', 0);
        $roundedTotal = MoneySupport::roundToCashIncrement($total, $increment);
        $roundingAdjustment = $roundedTotal->minus($total);

        return new CartTotals(
            lines: $pricedLines,
            subtotal: $subtotal,
            discountTotal: $discountTotal,
            taxTotal: $taxResult->totalTax,
            roundingAdjustment: $roundingAdjustment,
            total: $roundedTotal,
            costTotal: $costTotal,
            taxResult: $taxResult,
            appliedPromotions: $evaluation->applied,
        );
    }

    private function discountFor(Money $gross, string $value, string $type): Money
    {
        if (bccomp($value, '0', 4) === 0) {
            return MoneySupport::zero();
        }

        $computed = $type === 'fixed'
            ? MoneySupport::of($value)
            : MoneySupport::percentageOf($gross, $value);

        // A manual discount (unlike a promotion, which PromotionEngine already
        // clamps per line) is operator-entered and has no natural upper bound
        // from validation alone -- a mistyped 150% or a fixed amount larger
        // than the line must never be allowed to drive the line, and from
        // there the sale total, negative.
        return $computed->isGreaterThan($gross) ? $gross : $computed;
    }
}
