<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Data;

use Brick\Money\Money;

final readonly class SalesSummary
{
    public function __construct(
        public int $saleCount,
        public Money $subtotal,
        public Money $discountTotal,
        public Money $taxTotal,
        public Money $total,
        public Money $costTotal,
    ) {}

    public function margin(): Money
    {
        return $this->subtotal->minus($this->costTotal);
    }
}
