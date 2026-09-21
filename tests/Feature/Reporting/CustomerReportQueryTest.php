<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\CustomerReportQuery;
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
    $this->customer = Customer::firstOrFail();
    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function completeCustomerReportSale(?int $customerId, string $amount): Sale
{
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'customer_id' => $customerId,
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
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => $amount]);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('aggregates sale count and spend per customer', function () {
    completeCustomerReportSale($this->customer->id, '1.38');
    completeCustomerReportSale($this->customer->id, '1.38');

    $rows = app(CustomerReportQuery::class)->byCustomer(now()->subDay(), now()->addDay())->keyBy('customer_id');
    $row = $rows[$this->customer->id];

    expect((int) $row->sale_count)->toBe(2)
        ->and((string) $row->total->getAmount())->toBe('2.76');
});

it('groups sales with no customer under a Walk-in row', function () {
    completeCustomerReportSale(null, '1.38');

    $rows = app(CustomerReportQuery::class)->byCustomer(now()->subDay(), now()->addDay())->keyBy('customer_id');

    expect($rows[null]->customer_name)->toBe('Walk-in')
        ->and((int) $rows[null]->sale_count)->toBe(1);
});

it('excludes sales outside the date range', function () {
    completeCustomerReportSale($this->customer->id, '1.38');

    $rows = app(CustomerReportQuery::class)->byCustomer(now()->addDays(5), now()->addDays(10));

    expect($rows)->toHaveCount(0);
});
