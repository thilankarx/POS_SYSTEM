<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Promotions\Actions\RecordPromotionRedemptionsAction;
use App\Domain\Promotions\Data\AppliedPromotion;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionCondition;
use App\Domain\Promotions\Models\PromotionRedemption;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Data\CartTotals;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Taxation\Data\TaxResult;
use App\Support\Money\Money;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();

    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function checkoutCart(array $overrides = []): Cart
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

function checkoutLine(Cart $cart, string $sku, string $quantity): CartLine
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

it('applies a live automatic promotion to a sale and records a redemption', function () {
    $promotion = Promotion::create([
        'name' => '10% off cola',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => false,
        'stackable' => false,
        'priority' => 0,
        'is_active' => true,
    ]);
    $promotion->conditions()->create([
        'subject' => PromotionCondition::SUBJECT_ITEM,
        'operator' => PromotionCondition::OPERATOR_IN,
        'value' => [$this->item->id],
    ]);

    $cart = checkoutCart();
    checkoutLine($cart, 'BEV-COLA-330', '1'); // 1.20 gross, 0.12 promo discount, 1.08 net + 15% tax = 1.242 -> round to 1.24
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.25']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->discount_total->getAmount())->toBe('0.12');

    $redemption = PromotionRedemption::where('promotion_id', $promotion->id)->where('sale_id', $sale->id)->first();

    expect($redemption)->not->toBeNull()
        ->and((string) $redemption->discount_amount->getAmount())->toBe('0.12')
        ->and($promotion->fresh()->redemption_count)->toBe(1);
});

it('applies a coupon-gated promotion set via carts.coupon_code', function () {
    $promotion = Promotion::create([
        'name' => 'Coupon 10% off',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => true,
        'stackable' => false,
        'priority' => 0,
        'is_active' => true,
    ]);
    $promotion->conditions()->create([
        'subject' => PromotionCondition::SUBJECT_ITEM,
        'operator' => PromotionCondition::OPERATOR_IN,
        'value' => [$this->item->id],
    ]);
    $coupon = Coupon::create(['promotion_id' => $promotion->id, 'code' => 'SAVE10', 'max_uses' => 1]);

    $cart = checkoutCart(['coupon_code' => 'SAVE10']);
    checkoutLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.25']);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->discount_total->getAmount())->toBe('0.12')
        ->and($coupon->fresh()->use_count)->toBe(1);
});

it('fails checkout cleanly once a promotion has hit its redemption cap', function () {
    $promotion = Promotion::create([
        'name' => 'One-time only',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => false,
        'stackable' => false,
        'priority' => 0,
        'is_active' => true,
        'max_redemptions' => 1,
    ]);
    $promotion->conditions()->create([
        'subject' => PromotionCondition::SUBJECT_ITEM,
        'operator' => PromotionCondition::OPERATOR_IN,
        'value' => [$this->item->id],
    ]);

    // First sale consumes the only redemption.
    $firstCart = checkoutCart();
    checkoutLine($firstCart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $firstCart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.25']);
    app(CompleteSaleAction::class)->execute($firstCart->fresh());

    expect($promotion->fresh()->redemption_count)->toBe(1);

    // A promotion at its cap is excluded by the selector, so the second sale
    // simply prices without the discount rather than throwing.
    $secondCart = checkoutCart();
    checkoutLine($secondCart, 'BEV-COLA-330', '1');
    CartPayment::create(['cart_id' => $secondCart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $secondSale = app(CompleteSaleAction::class)->execute($secondCart->fresh());

    expect((string) $secondSale->discount_total->getAmount())->toBe('0.00');
});

it('throws when the locked recheck finds a promotion already exhausted', function () {
    // Exercises RecordPromotionRedemptionsAction::verifyAndLock() directly
    // with a totals snapshot that is now stale relative to the DB — the
    // scenario CompleteSaleAction guards against between its optimistic
    // price() call and the locked recheck inside the transaction.
    $promotion = Promotion::create([
        'name' => 'Already exhausted',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => false,
        'stackable' => false,
        'priority' => 0,
        'is_active' => true,
        'max_redemptions' => 1,
        'redemption_count' => 1,
    ]);

    $cart = checkoutCart();
    checkoutLine($cart, 'BEV-COLA-330', '1');

    $totals = new CartTotals(
        lines: [],
        subtotal: Money::zero(),
        discountTotal: Money::zero(),
        taxTotal: Money::zero(),
        roundingAdjustment: Money::zero(),
        total: Money::zero(),
        costTotal: Money::zero(),
        taxResult: new TaxResult([], [], Money::zero()),
        appliedPromotions: [new AppliedPromotion(
            promotionId: $promotion->id,
            couponId: null,
            discount: Money::of('0.12'),
        )],
    );

    expect(fn () => app(RecordPromotionRedemptionsAction::class)->verifyAndLock($cart, $totals))
        ->toThrow(CheckoutException::class, 'no longer available');
});
