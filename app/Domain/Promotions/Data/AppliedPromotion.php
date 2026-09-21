<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Data;

use Brick\Money\Money;

final readonly class AppliedPromotion
{
    public function __construct(
        public int $promotionId,
        public ?int $couponId,
        public Money $discount,
    ) {}
}
