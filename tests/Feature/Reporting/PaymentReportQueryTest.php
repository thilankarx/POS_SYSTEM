<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\PaymentReportQuery;
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
    $this->card = PaymentMethod::where('code', 'card')->firstOrFail();
    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function completePaymentReportSale(PaymentMethod $method, string $amount): Sale
{
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
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
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $method->id, 'amount' => $amount]);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('paginates payments filtered by date range, method and location', function () {
    completePaymentReportSale($this->cash, '1.38');
    completePaymentReportSale($this->card, '1.38');

    $query = app(PaymentReportQuery::class);

    expect($query->payments(now()->subDay(), now()->addDay())->total())->toBe(2)
        ->and($query->payments(now()->subDay(), now()->addDay(), paymentMethodId: $this->cash->id)->total())->toBe(1)
        ->and($query->payments(now()->subDay(), now()->addDay(), stockLocationId: $this->location->id)->total())->toBe(2)
        ->and($query->payments(now()->addDays(5), now()->addDays(10))->total())->toBe(0);
});

it('summarizes amount by method', function () {
    completePaymentReportSale($this->cash, '1.38');
    completePaymentReportSale($this->cash, '1.38');
    completePaymentReportSale($this->card, '1.38');

    $rows = app(PaymentReportQuery::class)->summaryByMethod(now()->subDay(), now()->addDay())->keyBy('method_name');

    expect((int) $rows['Cash']->payment_count)->toBe(2)
        ->and((string) $rows['Cash']->amount->getAmount())->toBe('2.76')
        ->and((int) $rows['Card']->payment_count)->toBe(1);
});

it('exports payments as a lazy collection with sale and method loaded', function () {
    $sale = completePaymentReportSale($this->cash, '1.38');

    $rows = app(PaymentReportQuery::class)->paymentsForExport(now()->subDay(), now()->addDay())->all();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->sale->id)->toBe($sale->id)
        ->and($rows[0]->method->code)->toBe('cash');
});
