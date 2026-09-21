<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionCondition;
use App\Domain\Sales\CartPricer;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
});

function promoCart(array $overrides = []): Cart
{
    $terminal = Terminal::where('code', 'T1')->firstOrFail();
    $user = User::where('username', 'cashier')->firstOrFail();

    return Cart::create(array_merge([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => $terminal->id,
        'stock_location_id' => $terminal->stock_location_id,
        'user_id' => $user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ], $overrides));
}

function promoLine(Cart $cart, string $sku, string $quantity): CartLine
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

function promotion(array $attributes, array $conditions): Promotion
{
    $promotion = Promotion::create(array_merge([
        'name' => 'Test promotion',
        'reward_type' => Promotion::REWARD_PERCENT_OFF,
        'reward_value' => '10',
        'requires_coupon' => false,
        'stackable' => false,
        'priority' => 0,
        'is_active' => true,
    ], $attributes));

    foreach ($conditions as $condition) {
        $promotion->conditions()->create($condition);
    }

    return $promotion;
}

// --- Promotion::isCurrentlyActive scheduling truth table -----------------

it('is inactive when is_active is false', function () {
    $promotion = Promotion::make(['is_active' => false]);

    expect($promotion->isCurrentlyActive(now()))->toBeFalse();
});

it('respects starts_at and ends_at', function () {
    $promotion = Promotion::make([
        'is_active' => true,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
    ]);

    expect($promotion->isCurrentlyActive(now()))->toBeFalse()
        ->and($promotion->isCurrentlyActive(now()->addDays(1)->addHours(12)))->toBeTrue()
        ->and($promotion->isCurrentlyActive(now()->addDays(3)))->toBeFalse();
});

it('respects active_days', function () {
    $monday = now()->next(Carbon::MONDAY)->setTime(12, 0);
    $tuesday = $monday->copy()->addDay();

    $promotion = Promotion::make(['is_active' => true, 'active_days' => '1']); // Monday only

    expect($promotion->isCurrentlyActive($monday))->toBeTrue()
        ->and($promotion->isCurrentlyActive($tuesday))->toBeFalse();
});

it('respects active_from and active_to time window', function () {
    $promotion = Promotion::make([
        'is_active' => true,
        'active_from' => '09:00:00',
        'active_to' => '17:00:00',
    ]);

    // active_from/active_to describe the store's own local business hours
    // (config('pos.timezone')), not UTC -- isCurrentlyActive() converts
    // whatever instant it's given to that timezone before comparing, so the
    // instants built here have to originate in it too, exactly like a real
    // "now" passed in from CartPricer would.
    $inWindow = Carbon::now(config('pos.timezone'))->setTime(12, 0);
    $beforeWindow = Carbon::now(config('pos.timezone'))->setTime(8, 0);
    $afterWindow = Carbon::now(config('pos.timezone'))->setTime(18, 0);

    expect($promotion->isCurrentlyActive($inWindow))->toBeTrue()
        ->and($promotion->isCurrentlyActive($beforeWindow))->toBeFalse()
        ->and($promotion->isCurrentlyActive($afterWindow))->toBeFalse();
});

it('has redemptions remaining until the cap is hit', function () {
    $promotion = Promotion::make(['max_redemptions' => 2, 'redemption_count' => 1]);
    expect($promotion->hasRedemptionsRemaining())->toBeTrue();

    $promotion = Promotion::make(['max_redemptions' => 2, 'redemption_count' => 2]);
    expect($promotion->hasRedemptionsRemaining())->toBeFalse();

    $promotion = Promotion::make(['max_redemptions' => null, 'redemption_count' => 999]);
    expect($promotion->hasRedemptionsRemaining())->toBeTrue();
});

// --- CartPricer integration: condition matching + reward math ------------

it('applies a percent_off promotion only to lines in the matched category', function () {
    $beverages = Category::where('slug', 'beverages')->firstOrFail();

    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10'],
        [['subject' => PromotionCondition::SUBJECT_CATEGORY, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$beverages->id]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1'); // 1.20, beverages
    promoLine($cart, 'BAK-CROIS', '1');    // 1.75, bakery

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    $colaLine = $totals->lineFor(1);
    $croissantLine = $totals->lineFor(2);

    expect((string) $colaLine->promotionDiscount->getAmount())->toBe('0.12')
        ->and((string) $croissantLine->promotionDiscount->getAmount())->toBe('0.00')
        ->and((string) $totals->discountTotal->getAmount())->toBe('0.12');
});

it('caps an amount_off discount at the line net so it cannot go negative', function () {
    promotion(
        ['reward_type' => Promotion::REWARD_AMOUNT_OFF, 'reward_value' => '5.00'],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BEV-COLA-330')->value('id')]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1'); // 1.20

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    $line = $totals->lineFor(1);

    expect((string) $line->promotionDiscount->getAmount())->toBe('1.20')
        ->and($line->net->isZero())->toBeTrue();
});

it('discounts down to a fixed_price reward', function () {
    promotion(
        ['reward_type' => Promotion::REWARD_FIXED_PRICE, 'reward_value' => '1.00'],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BEV-COLA-330')->value('id')]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1'); // 1.20 -> 1.00

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    $line = $totals->lineFor(1);

    expect((string) $line->promotionDiscount->getAmount())->toBe('0.20')
        ->and((string) $line->net->getAmount())->toBe('1.00');
});

it('does not apply a promotion whose item condition does not match', function () {
    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '50'],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BAK-CROIS')->value('id')]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');
});

// --- bogo / free_item ------------------------------------------------------

it('discounts the cheapest of two matching lines for a bogo promotion once the quantity threshold is met', function () {
    $beverages = Category::where('slug', 'beverages')->firstOrFail();

    promotion(
        ['reward_type' => Promotion::REWARD_BOGO, 'reward_value' => '0'],
        [
            ['subject' => PromotionCondition::SUBJECT_CATEGORY, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$beverages->id]],
            ['subject' => PromotionCondition::SUBJECT_QUANTITY, 'operator' => PromotionCondition::OPERATOR_GTE, 'value' => '2'],
        ],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');  // 1.20
    promoLine($cart, 'BEV-WATER-500', '1'); // 0.90, cheaper

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    $colaLine = $totals->lineFor(1);
    $waterLine = $totals->lineFor(2);

    expect((string) $waterLine->promotionDiscount->getAmount())->toBe('0.90')
        ->and($waterLine->net->isZero())->toBeTrue()
        ->and((string) $colaLine->promotionDiscount->getAmount())->toBe('0.00');
});

it('does not apply a bogo promotion when the quantity threshold is not met', function () {
    $beverages = Category::where('slug', 'beverages')->firstOrFail();

    promotion(
        ['reward_type' => Promotion::REWARD_BOGO, 'reward_value' => '0'],
        [
            ['subject' => PromotionCondition::SUBJECT_CATEGORY, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$beverages->id]],
            ['subject' => PromotionCondition::SUBJECT_QUANTITY, 'operator' => PromotionCondition::OPERATOR_GTE, 'value' => '2'],
        ],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');
});

it('discounts the reward item\'s own line for a free_item promotion, capped at its net', function () {
    $croissantId = Item::where('sku', 'BAK-CROIS')->value('id');

    promotion(
        ['reward_type' => Promotion::REWARD_FREE_ITEM, 'reward_value' => '0', 'reward_item_id' => $croissantId],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BEV-COLA-330')->value('id')]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');
    promoLine($cart, 'BAK-CROIS', '1'); // 1.75

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    $colaLine = $totals->lineFor(1);
    $croissantLine = $totals->lineFor(2);

    expect((string) $croissantLine->promotionDiscount->getAmount())->toBe('1.75')
        ->and($croissantLine->net->isZero())->toBeTrue()
        ->and((string) $colaLine->promotionDiscount->getAmount())->toBe('0.00');
});

it('does not apply a free_item promotion when the reward item is not in the cart', function () {
    $croissantId = Item::where('sku', 'BAK-CROIS')->value('id');

    promotion(
        ['reward_type' => Promotion::REWARD_FREE_ITEM, 'reward_value' => '0', 'reward_item_id' => $croissantId],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BEV-COLA-330')->value('id')]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');
});

it('combines two stackable promotions sequentially by descending priority', function () {
    $itemId = Item::where('sku', 'BEV-COLA-330')->value('id');

    // Applies first (higher priority): 10% of 1.20 = 0.12, net becomes 1.08.
    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10', 'stackable' => true, 'priority' => 10],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$itemId]]],
    );
    // Applies second: 0.10 off the now-reduced 1.08 net.
    promotion(
        ['reward_type' => Promotion::REWARD_AMOUNT_OFF, 'reward_value' => '0.10', 'stackable' => true, 'priority' => 5],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$itemId]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    expect((string) $totals->discountTotal->getAmount())->toBe('0.22')
        ->and((string) $totals->lineFor(1)->net->getAmount())->toBe('0.98');
});

it('applies only the highest-priority non-stackable promotion when two compete', function () {
    $itemId = Item::where('sku', 'BEV-COLA-330')->value('id');

    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10', 'stackable' => false, 'priority' => 5],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$itemId]]],
    );
    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '5', 'stackable' => false, 'priority' => 20],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$itemId]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1'); // 1.20

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    // Only the priority-20 promotion (5% off) should apply: 0.06.
    expect((string) $totals->discountTotal->getAmount())->toBe('0.06');
});

it('gates on a cart_total condition', function () {
    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10'],
        [['subject' => PromotionCondition::SUBJECT_CART_TOTAL, 'operator' => PromotionCondition::OPERATOR_GTE, 'value' => '2.00']],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1'); // 1.20, below threshold

    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');

    promoLine($cart, 'BAK-CROIS', '1'); // + 1.75 = 2.95, now above threshold
    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect((string) $totals->discountTotal->getAmount())->not->toBe('0.00');
});

it('gates on a quantity condition scoped to the matched item', function () {
    $itemId = Item::where('sku', 'BEV-COLA-330')->value('id');

    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10'],
        [
            ['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$itemId]],
            ['subject' => PromotionCondition::SUBJECT_QUANTITY, 'operator' => PromotionCondition::OPERATOR_GTE, 'value' => '3'],
        ],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '2');
    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');

    $cart2 = promoCart();
    promoLine($cart2, 'BEV-COLA-330', '3');
    $totals2 = app(CartPricer::class)->price($cart2->fresh(['lines.item']));
    expect((string) $totals2->discountTotal->getAmount())->not->toBe('0.00');
});

it('gates on customer_group and fails closed with no customer on the cart', function () {
    $group = LoyaltyPackage::create(['name' => 'Gold']);
    $customer = Customer::first();
    $customer->update(['loyalty_package_id' => $group->id]);

    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10'],
        [['subject' => PromotionCondition::SUBJECT_CUSTOMER_GROUP, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [$group->id]]],
    );

    $cartNoCustomer = promoCart();
    promoLine($cartNoCustomer, 'BEV-COLA-330', '1');
    $totals = app(CartPricer::class)->price($cartNoCustomer->fresh(['lines.item']));
    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');

    $cartWithCustomer = promoCart(['customer_id' => $customer->id]);
    promoLine($cartWithCustomer, 'BEV-COLA-330', '1');
    $totals2 = app(CartPricer::class)->price($cartWithCustomer->fresh(['lines.item', 'customer']));
    expect((string) $totals2->discountTotal->getAmount())->not->toBe('0.00');
});

it('only applies a coupon-gated promotion when a matching redeemable coupon code is set on the cart', function () {
    $promo = promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10', 'requires_coupon' => true],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BEV-COLA-330')->value('id')]]],
    );

    Coupon::create(['promotion_id' => $promo->id, 'code' => 'SAVE10', 'max_uses' => 1]);

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');
    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');

    $cart->update(['coupon_code' => 'SAVE10']);
    $totals2 = app(CartPricer::class)->price($cart->fresh(['lines.item']));
    expect((string) $totals2->discountTotal->getAmount())->not->toBe('0.00');
});

it('excludes a promotion that has hit its global redemption cap', function () {
    promotion(
        ['reward_type' => Promotion::REWARD_PERCENT_OFF, 'reward_value' => '10', 'max_redemptions' => 1, 'redemption_count' => 1],
        [['subject' => PromotionCondition::SUBJECT_ITEM, 'operator' => PromotionCondition::OPERATOR_IN, 'value' => [Item::where('sku', 'BEV-COLA-330')->value('id')]]],
    );

    $cart = promoCart();
    promoLine($cart, 'BEV-COLA-330', '1');
    $totals = app(CartPricer::class)->price($cart->fresh(['lines.item']));

    expect((string) $totals->discountTotal->getAmount())->toBe('0.00');
});
