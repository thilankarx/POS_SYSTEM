<?php

declare(strict_types=1);

use App\Support\Printing\Rules\ValidWindowsPrinterTarget;
use App\Support\Printing\WindowsPrinterTarget;

function validateWindowsPrinterTarget(string $value): bool
{
    $failed = false;
    (new ValidWindowsPrinterTarget)->validate('receipt_printer', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

it('normalises UNC paths to the smb:// form the connector understands', function (string $input, string $expected) {
    expect(WindowsPrinterTarget::normalize($input))->toBe($expected);
})->with([
    ['\\\\REGISTER-2\\XP80', 'smb://REGISTER-2/XP80'],
    ['//REGISTER-2/XP80', 'smb://REGISTER-2/XP80'],
    ['  \\\\register2.local\\Receipt Printer ', 'smb://register2.local/Receipt Printer'],
    ['smb://REGISTER-2/XP80', 'smb://REGISTER-2/XP80'],
    ['XP80', 'XP80'],
]);

it('composes a server-local queue when no host is given', function () {
    expect(WindowsPrinterTarget::compose(null, 'XP80'))->toBe('XP80')
        ->and(WindowsPrinterTarget::compose('  ', 'XP80'))->toBe('XP80')
        ->and(WindowsPrinterTarget::compose('\\\\REGISTER-2', 'XP80'))->toBe('smb://REGISTER-2/XP80');
});

it('splits a stored target back into host and share', function () {
    expect(WindowsPrinterTarget::split('smb://REGISTER-2/XP80'))->toBe(['host' => 'REGISTER-2', 'share' => 'XP80'])
        ->and(WindowsPrinterTarget::split('XP80'))->toBe(['host' => null, 'share' => 'XP80']);
});

it('accepts local and remote Windows printer targets', function (string $value) {
    expect(validateWindowsPrinterTarget($value))->toBeTrue();
})->with([
    'XP80',
    'Receipt Printer',
    'LPT1',
    'smb://REGISTER-2/XP80',
    '\\\\REGISTER-2\\XP80',
    'smb://register2.shop.lan/XP80',
]);

it('rejects malformed targets and embedded credentials', function (string $value) {
    expect(validateWindowsPrinterTarget($value))->toBeFalse();
})->with([
    'smb://user:secret@REGISTER-2/XP80',
    'XP80; del *',
    '\\\\REGISTER-2',
    'smb://REGISTER-2/',
]);
