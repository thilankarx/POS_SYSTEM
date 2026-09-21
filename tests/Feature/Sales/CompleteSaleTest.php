<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Exceptions\CheckoutException;
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

    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function makeCart(array $overrides = []): Cart
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

function addLine(Cart $cart, string $sku, string $quantity, ?string $price = null): CartLine
{
    $item = Item::where('sku', $sku)->firstOrFail();
    // A service item never has a stock lot -- it prices from its own
    // unit_price, with no cost tracked (mirrors AddCartLineAction).
    $unitPrice = $price ?? ($item->movesStock() ? demoPriceFor($item) : (string) $item->unit_price->getAmount());
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

it('completes a cash sale and records the totals', function () {
    $cart = makeCart();
    // Cola 1.20 x 2 = 2.40 @ 15% standard => 0.36 tax => 2.76
    addLine($cart, 'BEV-COLA-330', '2');

    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '2.76',
        'tendered' => '5.00',
    ]);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->subtotal->getAmount())->toBe('2.40')
        ->and((string) $sale->tax_total->getAmount())->toBe('0.36')
        ->and((string) $sale->total->getAmount())->toBe('2.76')
        ->and((string) $sale->paid_total->getAmount())->toBe('2.76')
        ->and($sale->status)->toBe(Sale::STATUS_COMPLETED)
        ->and($sale->number)->toStartWith('POS-')
        ->and($sale->lines)->toHaveCount(1)
        ->and($sale->payments)->toHaveCount(1);
});

it('does not add tax on top of a tax-inclusive price -- the exact regression class behind 1-C1', function () {
    // 1-C1: CartPricer read tax-inclusivity from a config key that never
    // existed (always false) while TaxEngine read the real, admin-toggled
    // setting -- so turning "prices include tax" on charged every customer
    // tax twice (once already inside the shelf price, once added again on
    // top). This is the one integration-level test that would have caught
    // it: no unit test of TaxEngine alone can, since TaxEngine itself was
    // never wrong -- the bug was CartPricer disagreeing with it.
    $settings = app(\App\Settings\TaxSettings::class);
    $settings->prices_include_tax = true;
    $settings->save();

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    StockLot::where('item_id', $item->id)->fefo()->firstOrFail()->update(['selling_price' => '115.00']); // 15% standard rate: 100.00 net + 15.00 tax, already inside the price.

    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '1');

    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '115.00',
    ]);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->subtotal->getAmount())->toBe('100.00')
        ->and((string) $sale->tax_total->getAmount())->toBe('15.00')
        ->and((string) $sale->total->getAmount())->toBe('115.00');
});

it('carries the price-override attribution from the cart line onto the sale line', function () {
    $manager = User::where('username', 'manager')->firstOrFail();

    $cart = makeCart();
    $line = addLine($cart, 'BEV-COLA-330', '1', '0.99');
    $line->update([
        'price_overridden' => true,
        'price_overridden_by_user_id' => $manager->id,
    ]);

    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->cash->id,
        'amount' => (string) app(\App\Domain\Sales\CartPricer::class)->price($cart->fresh(['lines.item']))->total->getAmount(),
    ]);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $saleLine = $sale->lines->firstOrFail();

    expect($saleLine->price_overridden)->toBeTrue()
        ->and($saleLine->price_overridden_by_user_id)->toBe($manager->id)
        ->and($saleLine->priceOverriddenBy->username)->toBe('manager');
});

it('refuses to complete a cart whose sale_type is return, even bypassing the request-layer guard', function () {
    // CreateOrResumeCartRequest is the only place a Cart is normally created,
    // and it already refuses sale_type=return there -- this proves the
    // action itself refuses it too, as a second, independent line of
    // defense against ever decrementing stock like a return while booking
    // positive revenue like a POS sale.
    $cart = makeCart(['sale_type' => Sale::TYPE_RETURN]);
    addLine($cart, 'BEV-COLA-330', '1');

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(CheckoutException::class);
});

it('records the change owed when the customer tenders more than the bill', function () {
    $cart = makeCart();
    // Cola 1.20 x 2 = 2.40 @ 15% => 0.36 tax => 2.76 due
    addLine($cart, 'BEV-COLA-330', '2');

    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '2.76',
        'tendered' => '5.00',
    ]);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->change_given->getAmount())->toBe('2.24');
});

it('records no change when the customer tenders the exact amount', function () {
    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '2');

    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '2.76',
        'tendered' => '2.76',
    ]);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->change_given->getAmount())->toBe('0.00');
});

it('marks the cart completed so it cannot be sold twice', function () {
    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $action = app(CompleteSaleAction::class);
    $action->execute($cart->fresh());

    expect($cart->fresh()->status)->toBe(Cart::STATUS_COMPLETED);

    $action->execute($cart->fresh());
})->throws(CheckoutException::class);

it('decrements stock and writes a ledger movement', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $before = $item->stockLevels()->where('stock_location_id', $this->location->id)->value('quantity');

    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '3');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '4.14']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $after = $item->stockLevels()->where('stock_location_id', $this->location->id)->value('quantity');

    expect(bcsub((string) $before, (string) $after, 3))->toBe('3.000');

    $movement = StockMovement::where('source_type', $sale->getMorphClass())
        ->where('source_id', $sale->id)
        ->where('item_id', $item->id)
        ->firstOrFail();

    expect((string) $movement->quantity_delta)->toBe('-3.000')
        ->and($movement->reason)->toBe(StockMovement::REASON_SALE);
});

it('never moves stock for a service item', function () {
    $cart = makeCart();
    addLine($cart, 'SRV-DELIVERY', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '5.75']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect(StockMovement::where('source_id', $sale->id)->where('source_type', $sale->getMorphClass())->count())->toBe(0);
});

it('refuses to complete when payment does not cover the total', function () {
    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '10');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.00']);

    app(CompleteSaleAction::class)->execute($cart->fresh());
})->throws(CheckoutException::class, 'Payments do not cover the total');

it('refuses to oversell', function () {
    $cart = makeCart();
    addLine($cart, 'BAK-BREAD-WHT', '9999');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '99999.00']);

    app(CompleteSaleAction::class)->execute($cart->fresh());
})->throws(CheckoutException::class, 'Insufficient stock');

it('refuses to complete against a closed shift', function () {
    $this->shift->update(['status' => Shift::STATUS_CLOSED, 'closed_at' => now()]);

    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());
})->throws(CheckoutException::class, 'shift');

it('refuses an empty cart', function () {
    app(CompleteSaleAction::class)->execute(makeCart());
})->throws(CheckoutException::class, 'no lines');

it('fires SaleCompleted so side effects stay out of the transaction', function () {
    Event::fake([SaleCompleted::class]);

    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    Event::assertDispatched(SaleCompleted::class, fn (SaleCompleted $e) => $e->sale->is($sale));
});

it('records zero tax for a zero-rated item', function () {
    $cart = makeCart();
    // Water 0.90 x 2 = 1.80, zero rated.
    addLine($cart, 'BEV-WATER-500', '2');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.80']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->tax_total->getAmount())->toBe('0.00')
        ->and((string) $sale->total->getAmount())->toBe('1.80')
        ->and($sale->taxes)->toHaveCount(0);
});

it('snapshots the item name so later catalog edits cannot rewrite history', function () {
    $cart = makeCart();
    addLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    Item::where('sku', 'BEV-COLA-330')->update(['name' => 'Renamed Cola']);

    expect($sale->lines()->first()->item_name)->toBe('Cola 330ml');
});
