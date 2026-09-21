<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Purchasing\PurchaseLinePricer;
use App\Domain\Taxation\Models\TaxCategory;

beforeEach(function () {
    $this->seed();
    $this->supplier = Supplier::firstOrFail();
    $this->taxed = Item::where('sku', 'BEV-COLA-330')->firstOrFail(); // tax_category 'standard', 15%
    $this->pricer = app(PurchaseLinePricer::class);
});

it('computes subtotal, tax, and total for a taxed item', function () {
    $totals = $this->pricer->price([
        ['item_id' => $this->taxed->id, 'quantity' => '10', 'unit_cost' => '0.40'],
    ]);

    expect((string) $totals->subtotal->getAmount())->toBe('4.00')
        ->and((string) $totals->taxTotal->getAmount())->toBe('0.60')
        ->and((string) $totals->total->getAmount())->toBe('4.60');
});

it('charges no tax for an item with no tax category', function () {
    $item = Item::create([
        'sku' => 'PRICER-NO-TAX',
        'name' => 'No Tax Item',
        'supplier_id' => $this->supplier->id,
        'tax_category_id' => null,
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ]);

    $totals = $this->pricer->price([
        ['item_id' => $item->id, 'quantity' => '2', 'unit_cost' => '5.00'],
    ]);

    expect((string) $totals->subtotal->getAmount())->toBe('10.00')
        ->and((string) $totals->taxTotal->getAmount())->toBe('0.00')
        ->and((string) $totals->total->getAmount())->toBe('10.00');
});

it('charges no tax for an item in the exempt category (no seeded rate)', function () {
    $exempt = TaxCategory::where('code', 'exempt')->firstOrFail();
    $item = Item::create([
        'sku' => 'PRICER-EXEMPT',
        'name' => 'Exempt Item',
        'supplier_id' => $this->supplier->id,
        'tax_category_id' => $exempt->id,
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ]);

    $totals = $this->pricer->price([
        ['item_id' => $item->id, 'quantity' => '1', 'unit_cost' => '20.00'],
    ]);

    expect((string) $totals->taxTotal->getAmount())->toBe('0.00');
});

it('sums multiple lines and mixed tax categories', function () {
    $noTax = Item::create([
        'sku' => 'PRICER-MIX',
        'name' => 'Mixed Item',
        'supplier_id' => $this->supplier->id,
        'tax_category_id' => null,
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ]);

    $totals = $this->pricer->price([
        ['item_id' => $this->taxed->id, 'quantity' => '10', 'unit_cost' => '0.40'],
        ['item_id' => $noTax->id, 'quantity' => '3', 'unit_cost' => '1.00'],
    ]);

    expect((string) $totals->subtotal->getAmount())->toBe('7.00')
        ->and((string) $totals->taxTotal->getAmount())->toBe('0.60')
        ->and((string) $totals->total->getAmount())->toBe('7.60');
});

it('skips a malformed line without throwing', function () {
    $totals = $this->pricer->price([
        ['item_id' => null, 'quantity' => '10', 'unit_cost' => '0.40'],
        ['item_id' => $this->taxed->id, 'quantity' => 'not-a-number', 'unit_cost' => '0.40'],
        [],
    ]);

    expect((string) $totals->subtotal->getAmount())->toBe('0.00')
        ->and((string) $totals->taxTotal->getAmount())->toBe('0.00');
});
