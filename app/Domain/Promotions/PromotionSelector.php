<?php

declare(strict_types=1);

namespace App\Domain\Promotions;

use App\Domain\Promotions\Models\Promotion;
use App\Domain\Sales\Models\Cart;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Loads promotion candidates for a cart. Every filter here is advisory,
 * read-time only — the same relationship CartPricer has to stock: it never
 * checks availability, only CompleteSaleAction does, under a lock. The
 * authoritative, race-safe recheck happens in RecordPromotionRedemptionsAction
 * inside the checkout transaction.
 */
final class PromotionSelector
{
    /** @return Collection<int, Promotion> */
    public function candidatesFor(Cart $cart, ?string $couponCode, CarbonInterface $at): Collection
    {
        return Promotion::query()
            ->where('is_active', true)
            ->with(['conditions', 'coupons'])
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->isCurrentlyActive($at))
            ->filter(fn (Promotion $promotion) => $promotion->hasRedemptionsRemaining())
            ->filter(fn (Promotion $promotion) => ! $promotion->requires_coupon
                || $this->matchingCoupon($promotion, $couponCode, $cart, $at) !== null)
            ->values();
    }

    private function matchingCoupon(Promotion $promotion, ?string $couponCode, Cart $cart, CarbonInterface $at): ?object
    {
        if ($couponCode === null || $couponCode === '') {
            return null;
        }

        $coupon = $promotion->coupons->firstWhere('code', $couponCode);

        if ($coupon === null || ! $coupon->isRedeemable($cart->customer_id, $at)) {
            return null;
        }

        return $coupon;
    }
}
