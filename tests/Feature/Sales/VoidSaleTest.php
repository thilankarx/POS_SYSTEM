<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Actions\RefundSaleAction;
use App\Domain\Sales\Actions\VoidSaleAction;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\ReturnReason;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = openShiftFor($this->user, $this->terminal);
});

function voidCart(array $overrides = []): Cart
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

function voidLine(Cart $cart, string $sku, string $quantity): CartLine
{
    $item = Item::where('sku', $sku)->firstOrFail();
    // A service item never has a stock lot -- it prices from its own
    // unit_price, with no cost tracked (mirrors AddCartLineAction).
    $unitPrice = $item->movesStock() ? demoPriceFor($item) : (string) $item->unit_price->getAmount();
    $costPrice = $item->movesStock() ? demoPriceFor($item, 'cost_price') : '0';

    return CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => $cart->nextLineNumber(),
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'cost_price' => $costPrice,
    ]);
}

it('voids a completed sale, restoring stock and voiding payments', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $before = $item->quantityAt($this->location);

    $cart = voidCart();
    voidLine($cart, 'BEV-COLA-330', '2');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '2.76']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $voided = app(VoidSaleAction::class)->execute($sale, 'Rung up the wrong customer', $this->user);

    expect($voided->status)->toBe(Sale::STATUS_VOIDED)
        ->and($voided->void_reason)->toBe('Rung up the wrong customer')
        ->and($voided->voided_by_user_id)->toBe($this->user->id)
        ->and($voided->voided_at)->not->toBeNull();

    expect($item->fresh()->quantityAt($this->location))->toBe($before);

    $movement = StockMovement::where('source_type', $sale->getMorphClass())
        ->where('source_id', $sale->id)
        ->where('reason', StockMovement::REASON_VOID)
        ->firstOrFail();
    expect((string) $movement->quantity_delta)->toBe('2.000');

    $payment = $voided->payments->first();
    expect($payment->status)->toBe(Payment::STATUS_VOIDED);
});

it('does not create a stock movement for a service line', function () {
    $cart = voidCart();
    voidLine($cart, 'SRV-DELIVERY', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '5.75']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    app(VoidSaleAction::class)->execute($sale, 'Customer cancelled', $this->user);

    expect(StockMovement::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)->where('reason', StockMovement::REASON_VOID)->count())->toBe(0);
});

it('refuses to void with a blank reason', function () {
    $cart = voidCart();
    voidLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    app(VoidSaleAction::class)->execute($sale, '   ', $this->user);
})->throws(CheckoutException::class, 'A reason is required');

it('refuses to void an already-voided sale', function () {
    $cart = voidCart();
    voidLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    app(VoidSaleAction::class)->execute($sale, 'First void', $this->user);
    app(VoidSaleAction::class)->execute($sale->fresh(), 'Second void', $this->user);
})->throws(CheckoutException::class, 'cannot be voided');

it('refuses to void a sale that has already been refunded', function () {
    $cart = voidCart();
    voidLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $line = $sale->lines->first();

    app(RefundSaleAction::class)->execute($sale, [$line->id => '1'], $reason->id, $this->cash->id, $this->user);

    app(VoidSaleAction::class)->execute($sale->fresh(), 'Too late', $this->user);
})->throws(CheckoutException::class, 'cannot be voided');
