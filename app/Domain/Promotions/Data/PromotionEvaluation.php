<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Data;

use Brick\Money\Money;

final readonly class PromotionEvaluation
{
    /**
     * @param  array<int, Money>  $perLineDiscount  keyed by cart line_number
     * @param  array<int, list<int>>  $promotionIdsByLine  keyed by cart line_number
     * @param  list<AppliedPromotion>  $applied
     */
    public function __construct(
        public array $perLineDiscount,
        public array $promotionIdsByLine,
        public array $applied,
    ) {}
}
