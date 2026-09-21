<?php

declare(strict_types=1);

namespace App\Domain\Taxation\Data;

use Brick\Money\Money;

/**
 * A single line handed to the tax engine. Deliberately a plain value object:
 * the engine must never touch an Eloquent model or the database, so it can be
 * exhaustively unit tested.
 */
final readonly class TaxableLine
{
    public function __construct(
        public int $lineNumber,
        public ?int $taxCategoryId,
        public Money $netAmount,
        public bool $isExempt = false,
    ) {}
}
