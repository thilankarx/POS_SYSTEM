<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Actions\RefundSaleAction;
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

function refundCart(array $overrides = []): Cart
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

function refundLine(Cart $cart, string $sku, string $quantity): CartLine
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

it('fully returns a single-line sale, restocking and refunding', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $before = $item->quantityAt($this->location);

    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $line = $sale->lines->first();

    $returnSale = app(RefundSaleAction::class)->execute(
        sale: $sale,
        lineQuantities: [$line->id => '1'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->user,
    );

    expect($returnSale->sale_type)->toBe(Sale::TYPE_RETURN)
        ->and($returnSale->returns_sale_id)->toBe($sale->id)
        ->and((string) $returnSale->subtotal->getAmount())->toBe('-1.20')
        ->and((string) $returnSale->tax_total->getAmount())->toBe('-0.18')
        ->and((string) $returnSale->total->getAmount())->toBe('-1.38')
        ->and((string) $returnSale->cost_total->getAmount())->toBe('-0.45');

    expect($sale->fresh()->status)->toBe(Sale::STATUS_REFUNDED)
        ->and((string) $line->fresh()->quantity_returned)->toBe('1.000');

    $movement = StockMovement::where('source_type', $returnSale->getMorphClass())
        ->where('source_id', $returnSale->id)
        ->firstOrFail();
    expect((string) $movement->quantity_delta)->toBe('1.000')
        ->and($movement->reason)->toBe(StockMovement::REASON_RETURN);

    expect($item->fresh()->quantityAt($this->location))->toBe($before);

    $payment = $returnSale->payments->first();
    expect((string) $payment->amount->getAmount())->toBe('-1.38')
        ->and($payment->status)->toBe(Payment::STATUS_REFUNDED)
        ->and($payment->refunds_payment_id)->toBe($sale->payments->first()->id);
});

it('partially returns a multi-quantity line and flips status back to refunded once the rest is returned', function () {
    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '2');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '2.76']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $reason = ReturnReason::where('code', 'changed_mind')->firstOrFail();
    $line = $sale->lines->first();

    $firstReturn = app(RefundSaleAction::class)->execute(
        sale: $sale,
        lineQuantities: [$line->id => '1'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->user,
    );

    expect((string) $firstReturn->total->getAmount())->toBe('-1.38')
        ->and($sale->fresh()->status)->toBe(Sale::STATUS_PARTIALLY_REFUNDED)
        ->and((string) $line->fresh()->quantity_returned)->toBe('1.000');

    app(RefundSaleAction::class)->execute(
        sale: $sale->fresh(),
        lineQuantities: [$line->id => '1'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->user,
    );

    expect($sale->fresh()->status)->toBe(Sale::STATUS_REFUNDED)
        ->and((string) $line->fresh()->quantity_returned)->toBe('2.000');
});

it('does not restock when the return reason does not restock', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $afterSale = $item->fresh()->quantityAt($this->location);

    $reason = ReturnReason::where('code', 'faulty')->firstOrFail();
    $line = $sale->lines->first();

    $returnSale = app(RefundSaleAction::class)->execute(
        sale: $sale,
        lineQuantities: [$line->id => '1'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->user,
    );

    expect(StockMovement::where('source_type', $returnSale->getMorphClass())->where('source_id', $returnSale->id)->count())->toBe(0)
        ->and($item->fresh()->quantityAt($this->location))->toBe($afterSale);
});

it('refuses to return more than remains on a line', function () {
    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $line = $sale->lines->first();

    app(RefundSaleAction::class)->execute(
        sale: $sale,
        lineQuantities: [$line->id => '2'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->user,
    );
})->throws(CheckoutException::class, 'remains');

it('refuses to refund an already fully-refunded sale', function () {
    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $line = $sale->lines->first();

    app(RefundSaleAction::class)->execute($sale, [$line->id => '1'], $reason->id, $this->cash->id, $this->user);

    app(RefundSaleAction::class)->execute($sale->fresh(), [$line->id => '1'], $reason->id, $this->cash->id, $this->user);
})->throws(CheckoutException::class, 'cannot be refunded');

it('refuses to refund a voided sale', function () {
    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $sale->update(['status' => Sale::STATUS_VOIDED]);
    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $line = $sale->lines->first();

    app(RefundSaleAction::class)->execute($sale->fresh(), [$line->id => '1'], $reason->id, $this->cash->id, $this->user);
})->throws(CheckoutException::class, 'cannot be refunded');

it('refuses a refund with no lines selected', function () {
    $cart = refundCart();
    refundLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();

    app(RefundSaleAction::class)->execute($sale, [], $reason->id, $this->cash->id, $this->user);
})->throws(CheckoutException::class, 'Select a quantity');
