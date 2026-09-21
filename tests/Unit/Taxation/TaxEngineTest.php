<?php

declare(strict_types=1);

use App\Domain\Taxation\Data\TaxableLine;
use App\Domain\Taxation\RateMatrix;
use App\Domain\Taxation\TaxEngine;
use App\Support\Money\Money;

/**
 * The tax engine is pure, so it is tested without a database at all.
 *
 * A fake stands in for the TaxRate model because RateMatrix only reads
 * id / rate / name / cascade_sequence / tax_category_id off it.
 */
function fakeRate(int $id, int $categoryId, string $rate, string $name = 'VAT', int $sequence = 0): object
{
    return new class($id, $categoryId, $rate, $name, $sequence)
    {
        public function __construct(
            public int $id,
            public int $tax_category_id,
            public string $rate,
            public string $name,
            public int $cascade_sequence,
        ) {}
    };
}

function engine(bool $inclusive = false): TaxEngine
{
    return new TaxEngine($inclusive);
}

it('applies an exclusive rate on top of the net amount', function () {
    $matrix = RateMatrix::fromRates([fakeRate(1, 10, '15.0000')]);

    $result = engine()->calculate(
        [new TaxableLine(1, 10, Money::of('100.00'))],
        $matrix
    );

    expect((string) $result->totalTax->getAmount())->toBe('15.00')
        ->and((string) $result->taxForLine(1)->getAmount())->toBe('15.00');
});

it('extracts an inclusive rate from inside the price', function () {
    // 115.00 gross at 15% inclusive => 15.00 tax, 100.00 net.
    $matrix = RateMatrix::fromRates([fakeRate(1, 10, '15.0000')]);

    $result = engine(inclusive: true)->calculate(
        [new TaxableLine(1, 10, Money::of('115.00'))],
        $matrix
    );

    expect((string) $result->totalTax->getAmount())->toBe('15.00');

    $applied = $result->forLine(1)[0];
    expect((string) $applied->taxableAmount->getAmount())->toBe('100.00')
        ->and($applied->isInclusive)->toBeTrue();
});

it('charges nothing on a zero rate', function () {
    $matrix = RateMatrix::fromRates([fakeRate(1, 20, '0.0000', 'Zero')]);

    $result = engine()->calculate(
        [new TaxableLine(1, 20, Money::of('49.99'))],
        $matrix
    );

    expect($result->totalTax->isZero())->toBeTrue()
        ->and($result->summary)->toBeEmpty();
});

it('skips lines belonging to an exempt customer', function () {
    $matrix = RateMatrix::fromRates([fakeRate(1, 10, '15.0000')]);

    $result = engine()->calculate(
        [new TaxableLine(1, 10, Money::of('100.00'), isExempt: true)],
        $matrix
    );

    expect($result->totalTax->isZero())->toBeTrue();
});

it('charges nothing for an item with no tax category', function () {
    $matrix = RateMatrix::fromRates([fakeRate(1, 10, '15.0000')]);

    $result = engine()->calculate(
        [new TaxableLine(1, null, Money::of('100.00'))],
        $matrix
    );

    expect($result->totalTax->isZero())->toBeTrue();
});

it('groups the same rate across lines into one summary row', function () {
    $matrix = RateMatrix::fromRates([fakeRate(1, 10, '15.0000', 'VAT')]);

    $result = engine()->calculate([
        new TaxableLine(1, 10, Money::of('100.00')),
        new TaxableLine(2, 10, Money::of('50.00')),
    ], $matrix);

    expect($result->summary)->toHaveCount(1)
        ->and((string) $result->summary[0]->taxAmount->getAmount())->toBe('22.50')
        ->and((string) $result->summary[0]->taxableAmount->getAmount())->toBe('150.00');
});

it('keeps separate rates as separate summary rows ordered by sequence', function () {
    $matrix = RateMatrix::fromRates([
        fakeRate(2, 10, '5.0000', 'City', sequence: 2),
        fakeRate(1, 10, '15.0000', 'State', sequence: 1),
    ]);

    $result = engine()->calculate(
        [new TaxableLine(1, 10, Money::of('100.00'))],
        $matrix
    );

    expect($result->summary)->toHaveCount(2)
        ->and($result->summary[0]->name)->toBe('State')
        ->and($result->summary[1]->name)->toBe('City')
        ->and((string) $result->totalTax->getAmount())->toBe('20.00');
});

it('rounds half up at the minor unit', function () {
    // 0.125 rounds to 0.13, not 0.12.
    $matrix = RateMatrix::fromRates([fakeRate(1, 10, '2.5000')]);

    $result = engine()->calculate(
        [new TaxableLine(1, 10, Money::of('5.00'))],
        $matrix
    );

    expect((string) $result->totalTax->getAmount())->toBe('0.13');
});
