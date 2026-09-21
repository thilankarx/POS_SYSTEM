<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\TaxReportQuery;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function completeTaxReportSale(string $sku, string $quantity, string $amount): Sale
{
    $item = Item::where('sku', $sku)->firstOrFail();
    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
    CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => $quantity,
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => $amount]);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('aggregates taxable amount and tax collected per rate', function () {
    // BEV-COLA-330: standard 15% tax. 2 x 1.20 = 2.40 subtotal, 0.36 tax, 2.76 total.
    completeTaxReportSale('BEV-COLA-330', '2', '2.76');

    $rows = app(TaxReportQuery::class)->byRate(now()->subDay(), now()->addDay());

    expect($rows)->toHaveCount(1)
        ->and((string) $rows->first()->taxable_amount->getAmount())->toBe('2.40')
        ->and((string) $rows->first()->tax_amount->getAmount())->toBe('0.36');
});

it('excludes zero-rated items from the collected total', function () {
    // BEV-WATER-500 carries the zero tax category.
    completeTaxReportSale('BEV-WATER-500', '1', '0.90');

    $rows = app(TaxReportQuery::class)->byRate(now()->subDay(), now()->addDay());

    $collected = $rows->sum(fn ($row) => (float) $row->tax_amount->getAmount());

    expect((float) $collected)->toBe(0.0);
});

it('excludes sales outside the date range', function () {
    completeTaxReportSale('BEV-COLA-330', '1', '1.38');

    $rows = app(TaxReportQuery::class)->byRate(now()->addDays(5), now()->addDays(10));

    expect($rows)->toHaveCount(0);
});
