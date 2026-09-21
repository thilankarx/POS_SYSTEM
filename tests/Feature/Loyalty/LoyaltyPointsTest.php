<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Loyalty\Actions\AdjustPointsAction;
use App\Domain\Loyalty\Exceptions\LoyaltyException;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Loyalty\Models\PointsTransaction;
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
    $this->points = PaymentMethod::where('code', 'points')->firstOrFail();

    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function loyaltyCustomer(?LoyaltyPackage $package = null, string $pointsBalance = '0'): Customer
{
    $person = Person::create(['first_name' => 'Loy', 'last_name' => 'Alty', 'email' => Str::random(8).'@example.test']);

    return Customer::create([
        'person_id' => $person->id,
        'company_name' => 'Loyalty Test Co',
        'loyalty_package_id' => $package?->id,
        'points_balance' => $pointsBalance,
    ]);
}

function loyaltyCart(array $overrides = []): Cart
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

function loyaltyLine(Cart $cart, string $sku, string $quantity): CartLine
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

it('earns points on a completed sale at the package rate', function () {
    $package = LoyaltyPackage::create([
        'name' => '5 per dollar',
        'points_per_currency_unit' => '5',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
    ]);
    $customer = loyaltyCustomer($package);

    $cart = loyaltyCart(['customer_id' => $customer->id]);
    loyaltyLine($cart, 'BEV-COLA-330', '1'); // 1.20 subtotal, no discount -> 6.000 points
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $customer->fresh()->points_balance)->toBe('6.000');

    $transaction = PointsTransaction::where('customer_id', $customer->id)->where('type', 'earn')->first();
    expect($transaction)->not->toBeNull()
        ->and((string) $transaction->points)->toBe('6.000');
});

it('earns nothing when no customer is attached', function () {
    $cart = loyaltyCart();
    loyaltyLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());

    expect(PointsTransaction::count())->toBe(0);
});

it('redeems points at checkout, debiting the balance and writing a ledger row', function () {
    $package = LoyaltyPackage::create([
        'name' => 'Redeemable',
        'points_per_currency_unit' => '0',
        'currency_value_per_point' => '0.01', // 100 points = $1.00
        'is_active' => true,
    ]);
    $customer = loyaltyCustomer($package, '500');

    $cart = loyaltyCart(['customer_id' => $customer->id]);
    loyaltyLine($cart, 'BEV-COLA-330', '1'); // total 1.38
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->points->id, 'amount' => '1.38']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->paid_total->getAmount())->toBe('1.38')
        ->and((string) $customer->fresh()->points_balance)->toBe('362.000'); // 500 - 138

    $transaction = PointsTransaction::where('customer_id', $customer->id)->where('type', 'redeem')->first();
    expect($transaction)->not->toBeNull()
        ->and((string) $transaction->points)->toBe('-138.000');
});

it('rejects checkout when the customer does not have enough points', function () {
    $package = LoyaltyPackage::create([
        'name' => 'Not enough',
        'points_per_currency_unit' => '0',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
    ]);
    $customer = loyaltyCustomer($package, '10'); // only $0.10 worth

    $cart = loyaltyCart(['customer_id' => $customer->id]);
    loyaltyLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->points->id, 'amount' => '1.38']);

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(LoyaltyException::class, 'enough points');

    expect((string) $customer->fresh()->points_balance)->toBe('10.000');
});

it('rejects a points payment when the cart has no customer attached', function () {
    $cart = loyaltyCart();
    loyaltyLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->points->id, 'amount' => '1.38']);

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(LoyaltyException::class);
});

it('manually adjusts a customer points balance, positive and negative', function () {
    $customer = loyaltyCustomer(null, '100');

    app(AdjustPointsAction::class)->execute($customer, '50', test()->user);
    expect((string) $customer->fresh()->points_balance)->toBe('150.000');

    app(AdjustPointsAction::class)->execute($customer->fresh(), '-30', test()->user);
    expect((string) $customer->fresh()->points_balance)->toBe('120.000');

    $transactions = PointsTransaction::where('customer_id', $customer->id)->where('type', 'adjustment')->get();
    expect($transactions)->toHaveCount(2)
        ->and($transactions->first()->user_id)->toBe(test()->user->id);
});

it('refuses a manual adjustment that would drop the balance below zero', function () {
    $customer = loyaltyCustomer(null, '10');

    expect(fn () => app(AdjustPointsAction::class)->execute($customer, '-20', test()->user))
        ->toThrow(LoyaltyException::class);

    expect((string) $customer->fresh()->points_balance)->toBe('10.000');
});
