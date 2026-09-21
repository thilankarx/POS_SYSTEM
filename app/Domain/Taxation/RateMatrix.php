<?php

declare(strict_types=1);

namespace App\Domain\Taxation;

use App\Domain\Taxation\Models\TaxRate;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * The rate lookup, resolved once and handed to the engine as data.
 *
 * Loading rates up front is what keeps the engine free of database access:
 * OSPOS re-queried tax tables inside its per-line loop, so pricing a cart of
 * 30 lines issued dozens of queries per keystroke.
 */
final class RateMatrix
{
    /** @param  Collection<int, TaxRate>  $rates */
    private function __construct(private readonly Collection $rates) {}

    public static function forDate(DateTimeInterface $date): self
    {
        return new self(
            TaxRate::query()
                ->effectiveOn($date)
                ->orderBy('cascade_sequence')
                ->get()
        );
    }

    /** @param  iterable<TaxRate>  $rates */
    public static function fromRates(iterable $rates): self
    {
        return new self(collect($rates));
    }

    /** @return Collection<int, TaxRate> */
    public function forCategory(?int $taxCategoryId): Collection
    {
        if ($taxCategoryId === null) {
            return collect();
        }

        return $this->rates->where('tax_category_id', $taxCategoryId)->values();
    }

    public function isEmpty(): bool
    {
        return $this->rates->isEmpty();
    }
}
