<?php

declare(strict_types=1);

namespace App\Domain\Taxation\Data;

use App\Support\Money\Money as MoneySupport;
use Brick\Money\Money;

/**
 * The engine's output: tax per line, plus the per-rate summary a receipt prints.
 */
final readonly class TaxResult
{
    /**
     * @param  array<int, list<AppliedTax>>  $lineTaxes  keyed by line number
     * @param  list<AppliedTax>  $summary
     */
    public function __construct(
        public array $lineTaxes,
        public array $summary,
        public Money $totalTax,
    ) {}

    public static function empty(): self
    {
        return new self([], [], MoneySupport::zero());
    }

    /** @return list<AppliedTax> */
    public function forLine(int $lineNumber): array
    {
        return $this->lineTaxes[$lineNumber] ?? [];
    }

    public function taxForLine(int $lineNumber): Money
    {
        $total = MoneySupport::zero();

        foreach ($this->forLine($lineNumber) as $tax) {
            $total = $total->plus($tax->taxAmount);
        }

        return $total;
    }
}
