<?php

declare(strict_types=1);

namespace App\Domain\Taxation\Data;

use Brick\Money\Money;

final readonly class AppliedTax
{
    public function __construct(
        public ?int $taxRateId,
        public string $name,
        public string $rate,
        public Money $taxableAmount,
        public Money $taxAmount,
        public bool $isInclusive,
        public int $printSequence = 0,
    ) {}
}
