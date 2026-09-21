<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\SalesReportQuery;
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

function reportCart(array $overrides = []): Cart
{
    return Cart::create(array_merge([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ], $overrides));
}

function reportLine(Cart $cart, string $sku, string $quantity): CartLine
{
    $item = Item::where('sku', $sku)->firstOrFail();

    return CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => $cart->nextLineNumber(),
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => $quantity,
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
}

function completeReportSale(Cart $cart, string $amount): Sale
{
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => $amount]);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('sums totals for completed revenue sales in range and excludes quotes/voids', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '2'); // 1.20 x 2 = 2.40 @ 15% => 0.36 tax => 2.76
    completeReportSale($cart, '2.76');

    // A quote must never reach the report (scopeRevenue excludes it).
    $quoteCart = reportCart(['sale_type' => Sale::TYPE_QUOTE]);
    reportLine($quoteCart, 'BEV-COLA-330', '5');
    app(CompleteSaleAction::class)->execute($quoteCart->fresh());

    // A voided sale must never reach the report (scopeCompleted excludes it).
    $voidedCart = reportCart();
    reportLine($voidedCart, 'BEV-COLA-330', '1');
    $voidedSale = completeReportSale($voidedCart, '1.38');
    $voidedSale->update(['status' => Sale::STATUS_VOIDED]);

    $summary = app(SalesReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary->saleCount)->toBe(1)
        ->and((string) $summary->subtotal->getAmount())->toBe('2.40')
        ->and((string) $summary->taxTotal->getAmount())->toBe('0.36')
        ->and((string) $summary->total->getAmount())->toBe('2.76');
});

it('computes margin as subtotal minus cost', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1'); // price 1.20, cost 0.45
    completeReportSale($cart, '1.38');

    $summary = app(SalesReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect((string) $summary->costTotal->getAmount())->toBe('0.45')
        ->and((string) $summary->margin()->getAmount())->toBe('0.75');
});

it('excludes sales outside the date range', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1');
    completeReportSale($cart, '1.38');

    $summary = app(SalesReportQuery::class)->summary(now()->addDays(5), now()->addDays(10));

    expect($summary->saleCount)->toBe(0)
        ->and($summary->total->isZero())->toBeTrue();
});

it('filters the summary by stock location', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1');
    completeReportSale($cart, '1.38');

    $otherLocation = StockLocation::where('code', 'WH')->firstOrFail();
    $summary = app(SalesReportQuery::class)->summary(now()->subDay(), now()->addDay(), $otherLocation->id);

    expect($summary->saleCount)->toBe(0);
});

it('groups totals by day', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1');
    completeReportSale($cart, '1.38');

    $rows = app(SalesReportQuery::class)->byDay(now()->subDay(), now()->addDay());

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->sale_count)->toBe(1);
});

it('groups totals by category', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1'); // beverages
    reportLine($cart, 'BAK-CROIS', '1');    // bakery
    completeReportSale($cart, '10.00');

    $rows = app(SalesReportQuery::class)->byCategory(now()->subDay(), now()->addDay());

    $categoryNames = $rows->pluck('category_name')->all();

    expect($categoryNames)->toContain('Beverages')
        ->and($categoryNames)->toContain('Bakery');
});

it('groups payments by method', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1');
    completeReportSale($cart, '1.38');

    $rows = app(SalesReportQuery::class)->byPaymentMethod(now()->subDay(), now()->addDay());

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->method_name)->toBe('Cash')
        ->and((string) $rows->first()->amount->getAmount())->toBe('1.38');
});

it('ranks top items by quantity sold', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '5');
    reportLine($cart, 'BAK-CROIS', '1');
    completeReportSale($cart, '20.00');

    $rows = app(SalesReportQuery::class)->topItems(now()->subDay(), now()->addDay());

    expect($rows->first()->item_name)->toBe('Cola 330ml');
});

it('exports one row per sale as a lazy collection', function () {
    $cart = reportCart();
    reportLine($cart, 'BEV-COLA-330', '1');
    $sale = completeReportSale($cart, '1.38');

    $rows = app(SalesReportQuery::class)->salesForExport(now()->subDay(), now()->addDay())->all();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->id)->toBe($sale->id);
});
