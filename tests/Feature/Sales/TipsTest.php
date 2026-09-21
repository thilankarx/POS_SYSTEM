<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\CommissionReportQuery;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Actions\RefundSaleAction;
use App\Domain\Sales\Actions\VoidSaleAction;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\ReturnReason;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use App\Support\Money\Money;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->waiter = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = openShiftFor($this->waiter, $this->terminal);
});

function tipCart(array $overrides = []): Cart
{
    return Cart::create(array_merge([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->waiter->id,
        'waiter_id' => test()->waiter->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ], $overrides));
}

function tipLine(Cart $cart, string $sku, string $quantity): CartLine
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

it('defaults tip_amount to zero when never set, with no error', function () {
    $cart = tipCart();
    tipLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->tip_amount->getAmount())->toBe('0.00');
});

it('folds the tip into what payments must cover before completion is allowed', function () {
    $cart = tipCart(['tip_amount' => '2.00']);
    tipLine($cart, 'BEV-COLA-330', '1'); // total 1.38
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());
})->throws(CheckoutException::class);

it('completes once payments cover total plus tip, recording the tip on the sale', function () {
    $cart = tipCart(['tip_amount' => '2.00']);
    tipLine($cart, 'BEV-COLA-330', '1'); // total 1.38
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '3.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->tip_amount->getAmount())->toBe('2.00')
        ->and((string) $sale->total->getAmount())->toBe('1.38')
        ->and((string) $sale->paid_total->getAmount())->toBe('3.38')
        ->and((string) $sale->change_given->getAmount())->toBe('0.00');
});

it('gives change correctly when payment exceeds total plus tip', function () {
    $cart = tipCart(['tip_amount' => '2.00']);
    tipLine($cart, 'BEV-COLA-330', '1'); // total 1.38
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '4.00']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->change_given->getAmount())->toBe('0.62');
});

it('never touches tip_amount on a refund -- a returned item does not claw back the tip', function () {
    $cart = tipCart(['tip_amount' => '2.00']);
    tipLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '3.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $line = $sale->lines->first();

    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $returnSale = app(RefundSaleAction::class)->execute(
        sale: $sale,
        lineQuantities: [$line->id => '1'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->waiter,
    );

    expect((string) $returnSale->tip_amount->getAmount())->toBe('0.00')
        ->and((string) $sale->fresh()->tip_amount->getAmount())->toBe('2.00');
});

it('excludes a voided sale entirely from the tips report', function () {
    $cart = tipCart(['tip_amount' => '2.00']);
    tipLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '3.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    app(VoidSaleAction::class)->execute($sale, 'Rung up by mistake', $this->waiter);

    $summary = app(CommissionReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary)->toBeEmpty();
});

it('aggregates tips per waiter across multiple sales in range', function () {
    foreach (range(1, 3) as $i) {
        $cart = tipCart(['tip_amount' => '1.00']);
        tipLine($cart, 'BEV-COLA-330', '1');
        CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '2.38']);
        app(CompleteSaleAction::class)->execute($cart->fresh());
    }

    $summary = app(CommissionReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary)->toHaveCount(1);
    $row = $summary->first();
    expect($row->id)->toBe($this->waiter->id)
        ->and((string) Money::of($row->total_tips)->getAmount())->toBe('3.00');
});
