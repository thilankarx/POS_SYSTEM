<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionRedemption;
use App\Domain\Sales\Data\CartTotals;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\Sale;

/**
 * Redemption limits (max_redemptions, max_redemptions_per_customer,
 * coupons.max_uses) are only advisory in CartPricer/PromotionSelector — the
 * authoritative, race-safe check happens here, under a row lock, inside
 * CompleteSaleAction's transaction. Mirrors CompleteSaleAction's own
 * lockForUpdate()-then-verify pattern for stock.
 */
final class RecordPromotionRedemptionsAction
{
    /**
     * @return array<int, Promotion> locked promotions keyed by promotion id
     */
    public function verifyAndLock(Cart $cart, CartTotals $totals): array
    {
        $locked = [];

        foreach ($totals->appliedPromotions as $applied) {
            $promotion = Promotion::query()->lockForUpdate()->findOrFail($applied->promotionId);

            if (! $promotion->hasRedemptionsRemaining()) {
                throw CheckoutException::promotionExhausted($promotion->name);
            }

            if ($promotion->max_redemptions_per_customer !== null && $cart->customer_id !== null) {
                $customerRedemptions = PromotionRedemption::query()
                    ->where('promotion_id', $promotion->id)
                    ->where('customer_id', $cart->customer_id)
                    ->count();

                if ($customerRedemptions >= $promotion->max_redemptions_per_customer) {
                    throw CheckoutException::promotionExhausted($promotion->name);
                }
            }

            if ($applied->couponId !== null) {
                $coupon = Coupon::query()->lockForUpdate()->findOrFail($applied->couponId);

                if (! $coupon->isRedeemable($cart->customer_id, now())) {
                    throw CheckoutException::promotionExhausted($promotion->name);
                }
            }

            $locked[$promotion->id] = $promotion;
        }

        return $locked;
    }

    /**
     * @param  array<int, Promotion>  $lockedPromotions
     */
    public function commit(Sale $sale, Cart $cart, array $lockedPromotions, CartTotals $totals): void
    {
        foreach ($totals->appliedPromotions as $applied) {
            PromotionRedemption::create([
                'promotion_id' => $applied->promotionId,
                'sale_id' => $sale->id,
                'coupon_id' => $applied->couponId,
                'customer_id' => $cart->customer_id,
                'discount_amount' => $applied->discount,
            ]);

            $lockedPromotions[$applied->promotionId]->increment('redemption_count');

            if ($applied->couponId !== null) {
                Coupon::whereKey($applied->couponId)->increment('use_count');
            }
        }
    }
}
