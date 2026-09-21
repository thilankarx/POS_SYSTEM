<?php

declare(strict_types=1);

use App\Support\Printing\Rules\ValidNetworkPrinterTarget;

function validatePrinterTarget(string $value): bool
{
    $failed = false;
    (new ValidNetworkPrinterTarget)->validate('receipt_printer', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

it('blocks loopback in every shorthand form the socket layer would still resolve', function (string $value) {
    expect(validatePrinterTarget($value))->toBeFalse();
})->with([
    '127.0.0.1',
    '127.0.0.1:9100',
    '127.1',
    '127.0.1',
    '0177.0.0.1',
    '0x7f000001',
    '2130706433',
    'localhost',
    'LOCALHOST',
]);

it('blocks link-local (cloud metadata) addresses', function (string $value) {
    expect(validatePrinterTarget($value))->toBeFalse();
})->with([
    '169.254.169.254',
    '169.254.1.1:80',
]);

it('blocks IPv6 loopback and link-local, bracketed or bare', function (string $value) {
    expect(validatePrinterTarget($value))->toBeFalse();
})->with([
    '::1',
    '[::1]:9100',
    'fe80::1',
]);

it('allows ordinary LAN printer targets', function (string $value) {
    expect(validatePrinterTarget($value))->toBeTrue();
})->with([
    '192.168.1.50',
    '192.168.1.50:9100',
    '10.0.0.5',
    'printer.local',
    'printer-01.lan:9100',
    '8.8.8.8',
    '0.0.0.0',
]);

it('rejects an out-of-range port', function () {
    expect(validatePrinterTarget('192.168.1.50:70000'))->toBeFalse();
    expect(validatePrinterTarget('192.168.1.50:0'))->toBeFalse();
});

it('allows a blank value (handled separately by required/requiredIf)', function () {
    expect(validatePrinterTarget(''))->toBeTrue();
});
