<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\CloseShiftAction;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Events\ShiftClosed;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();

    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->card = PaymentMethod::where('code', 'card')->firstOrFail();

    $this->shift = openShiftFor($this->user, $this->terminal, '100.00');
});

function sellOneCola(PaymentMethod $method, string $amount): Sale
{
    $cart = Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => $cart->nextLineNumber(),
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);

    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $method->id,
        'amount' => $amount,
    ]);

    return app(CompleteSaleAction::class)->execute($cart->fresh());
}

it('closes a shift with an exact cash count and zero variance', function () {
    Event::fake([ShiftClosed::class]);

    sellOneCola($this->cash, '1.38');

    // opening float 100.00 + 1.38 cash sale = 101.38
    $shift = app(CloseShiftAction::class)->execute($this->shift, $this->user, [
        ['denomination' => '100', 'count' => 1],
        ['denomination' => '1', 'count' => 1],
        ['denomination' => '0.25', 'count' => 1],
        ['denomination' => '0.1', 'count' => 1],
        ['denomination' => '0.01', 'count' => 3],
    ]);

    expect($shift->status)->toBe(Shift::STATUS_CLOSED)
        ->and((string) $shift->expected_cash->getAmount())->toBe('101.38')
        ->and((string) $shift->counted_cash->getAmount())->toBe('101.38')
        ->and((string) $shift->cash_variance->getAmount())->toBe('0.00')
        ->and($shift->cashCounts)->toHaveCount(5);

    Event::assertDispatched(ShiftClosed::class, fn (ShiftClosed $e) => $e->shift->is($shift));
});

it('excludes non-cash payments from expected cash', function () {
    sellOneCola($this->cash, '1.38');
    sellOneCola($this->card, '1.38');

    // Only the cash sale should count: 100.00 + 1.38 = 101.38, not 102.76.
    $shift = app(CloseShiftAction::class)->execute($this->shift, $this->user, [
        ['denomination' => '100', 'count' => 1],
        ['denomination' => '1', 'count' => 1],
        ['denomination' => '0.25', 'count' => 1],
        ['denomination' => '0.1', 'count' => 1],
        ['denomination' => '0.01', 'count' => 3],
    ]);

    expect((string) $shift->expected_cash->getAmount())->toBe('101.38');
});

it('records a negative variance when counted cash is short', function () {
    sellOneCola($this->cash, '1.38');

    $shift = app(CloseShiftAction::class)->execute($this->shift, $this->user, [
        ['denomination' => '100', 'count' => 1],
    ]);

    // expected 101.38, counted 100.00
    expect((string) $shift->cash_variance->getAmount())->toBe('-1.38');
});

it('records a positive variance when counted cash is over', function () {
    $shift = app(CloseShiftAction::class)->execute($this->shift, $this->user, [
        ['denomination' => '100', 'count' => 2],
    ]);

    // expected 100.00 (opening float only), counted 200.00
    expect((string) $shift->cash_variance->getAmount())->toBe('100.00');
});

it('refuses to close an already-closed shift', function () {
    app(CloseShiftAction::class)->execute($this->shift, $this->user, [['denomination' => '100', 'count' => 1]]);

    app(CloseShiftAction::class)->execute($this->shift->fresh(), $this->user, [['denomination' => '100', 'count' => 1]]);
})->throws(ShiftException::class, 'not open');

it('composes with CompleteSaleAction: a closed shift can no longer take sales', function () {
    sellOneCola($this->cash, '1.38');

    app(CloseShiftAction::class)->execute($this->shift, $this->user, [
        ['denomination' => '100', 'count' => 1],
        ['denomination' => '1', 'count' => 1],
        ['denomination' => '0.25', 'count' => 1],
        ['denomination' => '0.1', 'count' => 1],
        ['denomination' => '0.01', 'count' => 3],
    ]);

    sellOneCola($this->cash, '1.38');
})->throws(CheckoutException::class, 'shift');
