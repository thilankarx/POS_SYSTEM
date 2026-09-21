<?php

declare(strict_types=1);

namespace App\Domain\Sales\Data;

use Brick\Money\Money;

final readonly class PricedLine
{
    public function __construct(
        public int $lineNumber,
        public int $itemId,
        public Money $gross,          // quantity x unit price, before discount
        public Money $discount,       // manual line discount + promotion discount
        public Money $net,            // gross - discount
        public Money $tax,
        public Money $total,          // what the line contributes to the sale total
        public Money $cost,
        public Money $promotionDiscount,
        public array $appliedPromotionIds = [],
    ) {}
}
