<?php

declare(strict_types=1);

use App\Support\Money\Rules\ValidMoneyAmount;

function validateMoneyAmount(mixed $value): bool
{
    $failed = false;
    (new ValidMoneyAmount)->validate('amount', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

it('accepts amounts with up to 2 decimal places', function (string $value) {
    expect(validateMoneyAmount($value))->toBeTrue();
})->with(['0', '0.5', '0.05', '10', '10.5', '10.50', '999999999999.99']);

it('rejects an amount with more than 2 decimal places -- MoneyCast would silently truncate it', function (string $value) {
    expect(validateMoneyAmount($value))->toBeFalse();
})->with(['0.001', '0.0850', '10.567', '1.999']);

it('rejects a negative amount', function () {
    expect(validateMoneyAmount('-1.00'))->toBeFalse();
});

it('rejects a non-numeric value', function () {
    expect(validateMoneyAmount('abc'))->toBeFalse();
});
