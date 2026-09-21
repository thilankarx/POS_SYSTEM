<?php

declare(strict_types=1);

use App\Domain\Documents\DocumentNumberGenerator;

beforeEach(function () {
    $this->generator = app(DocumentNumberGenerator::class);
});

it('issues sequential, zero-padded numbers', function () {
    expect($this->generator->next('invoice'))->toBe('INV-000001')
        ->and($this->generator->next('invoice'))->toBe('INV-000002')
        ->and($this->generator->next('invoice'))->toBe('INV-000003');
});

it('keeps separate sequences independent', function () {
    $this->generator->next('invoice');
    $this->generator->next('invoice');

    expect($this->generator->next('quote'))->toBe('QUO-000001')
        ->and($this->generator->next('invoice'))->toBe('INV-000003');
});

it('scopes a sequence so it can reset yearly', function () {
    expect($this->generator->next('invoice', '2026'))->toBe('INV-2026-000001')
        ->and($this->generator->next('invoice', '2027'))->toBe('INV-2027-000001')
        ->and($this->generator->next('invoice', '2026'))->toBe('INV-2026-000002');
});

it('never issues the same number twice under repeated allocation', function () {
    $issued = [];

    for ($i = 0; $i < 250; $i++) {
        $issued[] = $this->generator->next('invoice');
    }

    expect($issued)->toHaveCount(250)
        ->and(array_unique($issued))->toHaveCount(250);
});
