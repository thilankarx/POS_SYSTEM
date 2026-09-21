<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Data;

use Brick\Money\Money;

final readonly class PurchaseLinesTotal
{
    public function __construct(
        public Money $subtotal,
        public Money $taxTotal,
        public Money $total,
    ) {}
}
