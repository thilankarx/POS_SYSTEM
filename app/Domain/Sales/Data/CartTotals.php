<?php

declare(strict_types=1);

namespace App\Domain\Sales\Data;

use App\Domain\Promotions\Data\AppliedPromotion;
use App\Domain\Taxation\Data\TaxResult;
use Brick\Money\Money;

final readonly class CartTotals
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  list<AppliedPromotion>  $appliedPromotions
     */
    public function __construct(
        public array $lines,
        public Money $subtotal,
        public Money $discountTotal,
        public Money $taxTotal,
        public Money $roundingAdjustment,
        public Money $total,
        public Money $costTotal,
        public TaxResult $taxResult,
        public array $appliedPromotions = [],
    ) {}

    public function lineFor(int $lineNumber): ?PricedLine
    {
        foreach ($this->lines as $line) {
            if ($line->lineNumber === $lineNumber) {
                return $line;
            }
        }

        return null;
    }
}
