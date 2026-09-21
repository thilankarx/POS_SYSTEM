<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\CommissionReportQuery;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Actions\CreateOrResumeCartAction;
use App\Domain\Sales\Actions\RefundSaleAction;
use App\Domain\Sales\Actions\VoidSaleAction;
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
    $this->waiter->update(['commission_rate' => '10.00']);
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = openShiftFor($this->waiter, $this->terminal);
});

function commissionCart(array $overrides = []): Cart
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

function commissionLine(Cart $cart, string $sku, string $quantity): CartLine
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

it('sets waiter_id to the creating user and never reattributes it on resume, unlike user_id', function () {
    $clientUuid = (string) Str::uuid();
    $opener = $this->waiter;
    $resumer = User::factory()->create(['is_active' => true]);
    $resumer->assignRole('Cashier');
    $resumer->stockLocations()->syncWithoutDetaching([$this->location->id]);

    $cart = app(CreateOrResumeCartAction::class)->execute(
        user: $opener,
        terminal: $this->terminal,
        clientUuid: $clientUuid,
    );

    expect($cart->waiter_id)->toBe($opener->id)
        ->and($cart->user_id)->toBe($opener->id);

    $cart->update(['status' => Cart::STATUS_SUSPENDED, 'suspended_at' => now()]);
    openShiftFor($resumer, $this->terminal);

    $resumed = app(CreateOrResumeCartAction::class)->execute(
        user: $resumer,
        terminal: $this->terminal,
        clientUuid: $clientUuid,
    );

    expect($resumed->user_id)->toBe($resumer->id)
        ->and($resumed->waiter_id)->toBe($opener->id);
});

it('computes commission_amount on the completed sale from the waiter rate frozen at that moment', function () {
    $cart = commissionCart();
    commissionLine($cart, 'BEV-COLA-330', '1'); // unit_price 1.20
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->commission_rate_applied)->toBe('10.00')
        ->and((string) $sale->commission_amount->getAmount())->toBe('0.12'); // 10% of 1.20 subtotal
});

it('produces zero commission when the waiter has no rate set, without erroring', function () {
    $this->waiter->update(['commission_rate' => null]);
    $cart = commissionCart();
    commissionLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect($sale->commission_rate_applied)->toBeNull()
        ->and((string) $sale->commission_amount->getAmount())->toBe('0.00');
});

it('produces zero commission when the cart has no waiter at all', function () {
    $cart = commissionCart(['waiter_id' => null]);
    commissionLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect($sale->waiter_id)->toBeNull()
        ->and((string) $sale->commission_amount->getAmount())->toBe('0.00');
});

it('reverses commission via a negative amount on a full refund, netting to zero', function () {
    $cart = commissionCart();
    commissionLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
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

    expect($returnSale->waiter_id)->toBe($this->waiter->id)
        ->and((string) $returnSale->commission_amount->getAmount())->toBe('-0.12')
        ->and($sale->commission_amount->plus($returnSale->commission_amount)->isZero())->toBeTrue();
});

it('nets a partial refund proportionally against the original rate, not the waiters current rate', function () {
    $cart = commissionCart();
    commissionLine($cart, 'BEV-COLA-330', '2');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '2.76']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $line = $sale->lines->first();

    // Rate changes after the sale -- the refund must still use the 10%
    // that was actually applied at sale time, not this new 50%.
    $this->waiter->update(['commission_rate' => '50.00']);

    $reason = ReturnReason::where('code', 'wrong_item')->firstOrFail();
    $returnSale = app(RefundSaleAction::class)->execute(
        sale: $sale,
        lineQuantities: [$line->id => '1'],
        returnReasonId: $reason->id,
        refundPaymentMethodId: $this->cash->id,
        user: $this->waiter,
    );

    expect((string) $returnSale->commission_rate_applied)->toBe('10.00')
        ->and((string) $returnSale->commission_amount->getAmount())->toBe('-0.12');
});

it('excludes a voided sale entirely from the commission report', function () {
    $cart = commissionCart();
    commissionLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    app(VoidSaleAction::class)->execute($sale, 'Rung up by mistake', $this->waiter);

    $summary = app(CommissionReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary)->toBeEmpty();
});

it('aggregates commission per waiter across multiple sales in range', function () {
    foreach (range(1, 3) as $i) {
        $cart = commissionCart();
        commissionLine($cart, 'BEV-COLA-330', '1');
        CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
        app(CompleteSaleAction::class)->execute($cart->fresh());
    }

    $summary = app(CommissionReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary)->toHaveCount(1);
    $row = $summary->first();
    expect($row->id)->toBe($this->waiter->id)
        ->and($row->sale_count)->toBe(3)
        ->and((string) Money::of($row->total_commission)->getAmount())->toBe('0.36');
});

it('excludes sales outside the requested date range', function () {
    $cart = commissionCart();
    commissionLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    app(CompleteSaleAction::class)->execute($cart->fresh());

    $summary = app(CommissionReportQuery::class)->summary(now()->addDays(5), now()->addDays(10));

    expect($summary)->toBeEmpty();
});
