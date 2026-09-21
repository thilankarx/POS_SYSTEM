<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Domain\Catalog\Models\Item;
use App\Domain\Purchasing\Data\PurchaseLinesTotal;
use App\Domain\Taxation\Data\TaxableLine;
use App\Domain\Taxation\RateMatrix;
use App\Domain\Taxation\TaxEngine;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;

/**
 * Prices a purchase order / receiving line set: subtotal, tax, total.
 *
 * Mirrors CartPricer's role for the Sales side, minus promotions and
 * customer exemption (a PO/receiving has neither) — tax category always
 * comes from the current Item, never stored on the line itself, same as
 * cart_lines.
 */
final class PurchaseLinePricer
{
    public function __construct(private readonly TaxEngine $taxEngine) {}

    /**
     * @param  array<int, array{item_id?: mixed, quantity?: mixed, unit_cost?: mixed}>  $lines
     */
    public function price(array $lines): PurchaseLinesTotal
    {
        $itemIds = array_filter(array_column($lines, 'item_id'));
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        $subtotal = MoneySupport::zero();
        $taxableLines = [];

        foreach (array_values($lines) as $index => $line) {
            if (empty($line['item_id']) || ! is_numeric($line['quantity'] ?? null) || ! is_numeric($line['unit_cost'] ?? null)) {
                continue;
            }

            $lineTotal = MoneySupport::of((string) $line['unit_cost'])->multipliedBy((string) $line['quantity'], RoundingMode::HalfUp);
            $subtotal = $subtotal->plus($lineTotal);

            $taxableLines[] = new TaxableLine(
                lineNumber: $index + 1,
                taxCategoryId: $items->get((int) $line['item_id'])?->tax_category_id,
                netAmount: $lineTotal,
            );
        }

        $taxResult = $this->taxEngine->calculate($taxableLines, RateMatrix::forDate(now()));

        return new PurchaseLinesTotal(
            subtotal: $subtotal,
            taxTotal: $taxResult->totalTax,
            total: $subtotal->plus($taxResult->totalTax),
        );
    }
}
