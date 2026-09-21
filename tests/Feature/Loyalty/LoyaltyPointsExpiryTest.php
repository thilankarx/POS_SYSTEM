<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Loyalty\Actions\AdjustPointsAction;
use App\Domain\Loyalty\Actions\ConsumeEarnBatchesAction;
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
use Illuminate\Support\Facades\Artisan;
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

function expiryTestCustomer(?LoyaltyPackage $package = null, string $pointsBalance = '0'): Customer
{
    $person = Person::create(['first_name' => 'Exp', 'last_name' => 'Iry', 'email' => Str::random(8).'@example.test']);

    return Customer::create([
        'person_id' => $person->id,
        'company_name' => 'Expiry Test Co',
        'loyalty_package_id' => $package?->id,
        'points_balance' => $pointsBalance,
    ]);
}

function expiryTestCart(array $overrides = []): Cart
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

function expiryTestLine(Cart $cart, string $sku, string $quantity): CartLine
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

/** Creates an earn-type ledger row with a controlled created_at, bypassing AwardLoyaltyPointsAction. */
function backdatedEarnBatch(Customer $customer, string $points, ?LoyaltyPackage $package, DateTimeInterface $createdAt, ?DateTimeInterface $expiresAt = null): PointsTransaction
{
    $transaction = PointsTransaction::create([
        'customer_id' => $customer->id,
        'loyalty_package_id' => $package?->id,
        'type' => 'earn',
        'points' => $points,
        'balance_after' => $points,
        'expires_at' => $expiresAt,
        'remaining_points' => $points,
    ]);
    $transaction->update(['created_at' => $createdAt]);

    return $transaction->fresh();
}

it('sets expires_at and remaining_points on an earn row when the package has an expiry window', function () {
    $package = LoyaltyPackage::create([
        'name' => 'Expiring',
        'points_per_currency_unit' => '5',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
        'points_expire_after_days' => 30,
    ]);
    $customer = expiryTestCustomer($package);

    $cart = expiryTestCart(['customer_id' => $customer->id]);
    expiryTestLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());

    $transaction = PointsTransaction::where('customer_id', $customer->id)->where('type', 'earn')->firstOrFail();

    expect($transaction->expires_at)->not->toBeNull()
        ->and($transaction->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and((string) $transaction->remaining_points)->toBe('6.000');
});

it('leaves expires_at and remaining_points null when the package has no expiry window', function () {
    $package = LoyaltyPackage::create([
        'name' => 'Non-expiring',
        'points_per_currency_unit' => '5',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
    ]);
    $customer = expiryTestCustomer($package);

    $cart = expiryTestCart(['customer_id' => $customer->id]);
    expiryTestLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->cash->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());

    $transaction = PointsTransaction::where('customer_id', $customer->id)->where('type', 'earn')->firstOrFail();

    expect($transaction->expires_at)->toBeNull();
});

it('consumes earn batches oldest-first, including a partial consumption', function () {
    $customer = expiryTestCustomer(null, '150');

    $older = backdatedEarnBatch($customer, '50', null, now()->subDays(10));
    $newer = backdatedEarnBatch($customer, '100', null, now()->subDays(2));

    app(ConsumeEarnBatchesAction::class)->execute($customer, '80');

    expect((string) $older->fresh()->remaining_points)->toBe('0.000')
        ->and((string) $newer->fresh()->remaining_points)->toBe('70.000');
});

it('consumes the redeemed earn batch via checkout redemption', function () {
    $package = LoyaltyPackage::create([
        'name' => 'Redeemable',
        'points_per_currency_unit' => '0',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
    ]);
    $customer = expiryTestCustomer($package, '500');
    $batch = backdatedEarnBatch($customer, '500', $package, now()->subDay());

    $cart = expiryTestCart(['customer_id' => $customer->id]);
    expiryTestLine($cart, 'BEV-COLA-330', '1'); // total 1.38 -> 138 points
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => test()->points->id, 'amount' => '1.38']);

    app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $batch->fresh()->remaining_points)->toBe('362.000');
});

it('consumes earn batches via a negative manual adjustment', function () {
    $customer = expiryTestCustomer(null, '100');
    $batch = backdatedEarnBatch($customer, '100', null, now()->subDay());

    app(AdjustPointsAction::class)->execute($customer, '-40', test()->user);

    expect((string) $batch->fresh()->remaining_points)->toBe('60.000');
});

it('sets remaining_points on a positive manual adjustment but never expires_at', function () {
    $customer = expiryTestCustomer(null, '10');

    app(AdjustPointsAction::class)->execute($customer, '50', test()->user);

    $transaction = PointsTransaction::where('customer_id', $customer->id)->where('type', 'adjustment')->firstOrFail();

    expect((string) $transaction->remaining_points)->toBe('50.000')
        ->and($transaction->expires_at)->toBeNull();
});

it('expires only the due, unconsumed remainder of a batch and leaves the balance consistent', function () {
    $customer = expiryTestCustomer(null, '100');
    $due = backdatedEarnBatch($customer, '100', null, now()->subDays(40), now()->subDay());
    $notDue = backdatedEarnBatch($customer, '50', null, now()->subDays(5), now()->addDays(10));
    $customer->update(['points_balance' => '150']);

    Artisan::call('loyalty:expire-points');

    expect((string) $due->fresh()->remaining_points)->toBe('0.000')
        ->and((string) $notDue->fresh()->remaining_points)->toBe('50.000')
        ->and((string) $customer->fresh()->points_balance)->toBe('50.000');

    $expireRow = PointsTransaction::where('customer_id', $customer->id)->where('type', 'expire')->firstOrFail();
    expect((string) $expireRow->points)->toBe('-100.000');
});

it('only expires the leftover of a partially-redeemed batch, not its original amount', function () {
    $customer = expiryTestCustomer(null, '100');
    $batch = backdatedEarnBatch($customer, '100', null, now()->subDays(40), now()->subDay());
    $customer->update(['points_balance' => '100']);

    app(ConsumeEarnBatchesAction::class)->execute($customer, '30');
    $customer->update(['points_balance' => '70']);

    Artisan::call('loyalty:expire-points');

    expect((string) $batch->fresh()->remaining_points)->toBe('0.000')
        ->and((string) $customer->fresh()->points_balance)->toBe('0.000');

    $expireRow = PointsTransaction::where('customer_id', $customer->id)->where('type', 'expire')->firstOrFail();
    expect((string) $expireRow->points)->toBe('-70.000');
});

it('is idempotent when run twice', function () {
    $customer = expiryTestCustomer(null, '100');
    backdatedEarnBatch($customer, '100', null, now()->subDays(40), now()->subDay());
    $customer->update(['points_balance' => '100']);

    Artisan::call('loyalty:expire-points');
    expect(PointsTransaction::where('customer_id', $customer->id)->where('type', 'expire')->count())->toBe(1);

    Artisan::call('loyalty:expire-points');
    expect(PointsTransaction::where('customer_id', $customer->id)->where('type', 'expire')->count())->toBe(1);
});
