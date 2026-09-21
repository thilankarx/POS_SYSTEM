<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Models\Customer;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Giftcards\Models\GiftcardTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Loyalty\Models\PointsTransaction;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionRedemption;
use App\Domain\Sales\Models\Sale;
use Brick\Math\RoundingMode;

/**
 * Reverses the store-value side effects of a completed sale -- a gift-card
 * debit, points redeemed, points earned, and promotion/coupon redemption
 * slots -- when that sale is voided or refunded. Neither VoidSaleAction nor
 * RefundSaleAction touched any of this before: a sale paid off with a gift
 * card or loyalty points could be refunded without ever crediting the
 * instrument back, permanently losing that value, and a refunded sale's
 * earned points stayed spendable forever.
 *
 * Must run inside the caller's own transaction, against rows already locked
 * (or about to be locked here) as part of that same transaction, so the
 * reversal commits or rolls back with the rest of the void/refund.
 */
final class ReverseSaleRedemptionsAction
{
    /**
     * @param  string  $share  bcmath ratio in (0,1] of the sale being undone
     *                         -- "1" for a void or a full refund, a smaller
     *                         fraction for a partial refund.
     * @param  bool  $reverseGiftcard  whether to credit back the gift card(s)
     *                                 this sale redeemed. For a refund this
     *                                 must be gated to only fire when the
     *                                 operator explicitly chose to refund via
     *                                 the gift-card method -- crediting the
     *                                 card back while ALSO paying the refund
     *                                 out through a different method would
     *                                 double the customer's money back.
     * @param  bool  $reversePoints  same gate, for points redeemed
     * @param  bool  $reversePromotions  whether to release promotion/coupon
     *                                   redemption slots. Only well-defined
     *                                   for a full reversal (void): which
     *                                   slot a *partial* refund should
     *                                   release is ambiguous, so refunds
     *                                   never pass this.
     */
    public function execute(
        Sale $sale,
        string $share,
        User $user,
        bool $reverseGiftcard,
        bool $reversePoints,
        bool $reversePromotions,
    ): void {
        if ($reverseGiftcard) {
            $this->reverseGiftcards($sale, $share, $user);
        }

        if ($reversePoints) {
            $this->reversePointsRedeemed($sale, $share, $user);
        }

        $this->clawBackEarnedPoints($sale, $share, $user);

        if ($reversePromotions) {
            $this->reversePromotions($sale);
        }
    }

    private function reverseGiftcards(Sale $sale, string $share, User $user): void
    {
        GiftcardTransaction::where('sale_id', $sale->id)
            ->where('type', 'redeem')
            ->get()
            ->each(function (GiftcardTransaction $original) use ($sale, $share, $user) {
                // Locked, not just read: two refunds against the same sale's
                // gift card (unlikely, but the same class of race every other
                // redemption in this codebase already guards) must not both
                // read the pre-credit balance.
                $giftcard = Giftcard::query()->lockForUpdate()->find($original->giftcard_id);

                if ($giftcard === null) {
                    return;
                }

                $credit = $original->amount->abs()->multipliedBy($share, RoundingMode::HalfUp);

                if ($credit->isZero()) {
                    return;
                }

                $balanceAfter = $giftcard->balance->plus($credit);

                GiftcardTransaction::create([
                    'giftcard_id' => $giftcard->id,
                    'sale_id' => $sale->id,
                    'user_id' => $user->id,
                    'type' => 'refund',
                    'amount' => $credit,
                    'balance_after' => $balanceAfter,
                ]);

                $giftcard->update(['balance' => $balanceAfter]);
            });
    }

    private function reversePointsRedeemed(Sale $sale, string $share, User $user): void
    {
        PointsTransaction::where('sale_id', $sale->id)
            ->where('type', 'redeem')
            ->get()
            ->each(function (PointsTransaction $original) use ($share, $user) {
                $customer = Customer::query()->lockForUpdate()->find($original->customer_id);

                if ($customer === null) {
                    return;
                }

                $magnitude = $this->magnitude((string) $original->points);
                $credit = bcmul($magnitude, $share, 3);

                if (bccomp($credit, '0', 3) <= 0) {
                    return;
                }

                $balanceAfter = bcadd((string) $customer->points_balance, $credit, 3);

                PointsTransaction::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $original->sale_id,
                    'loyalty_package_id' => $original->loyalty_package_id,
                    'user_id' => $user->id,
                    'type' => 'refund',
                    'points' => $credit,
                    'balance_after' => $balanceAfter,
                ]);

                $customer->update(['points_balance' => $balanceAfter]);
            });
    }

    /**
     * Independent of which method the refund is paid out through -- the
     * points this sale earned must not stay spendable once the sale that
     * earned them is undone. Clamped at the customer's current balance: if
     * they already spent the earned points elsewhere, claw back only what
     * is left rather than driving the balance negative.
     */
    private function clawBackEarnedPoints(Sale $sale, string $share, User $user): void
    {
        PointsTransaction::where('sale_id', $sale->id)
            ->where('type', 'earn')
            ->get()
            ->each(function (PointsTransaction $original) use ($sale, $share, $user) {
                $customer = Customer::query()->lockForUpdate()->find($original->customer_id);

                if ($customer === null) {
                    return;
                }

                $magnitude = $this->magnitude((string) $original->points);
                $wanted = bcmul($magnitude, $share, 3);
                $available = (string) $customer->points_balance;
                $clawback = bccomp($wanted, $available, 3) > 0 ? $available : $wanted;

                if (bccomp($clawback, '0', 3) <= 0) {
                    return;
                }

                $balanceAfter = bcsub($available, $clawback, 3);

                PointsTransaction::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'loyalty_package_id' => $original->loyalty_package_id,
                    'user_id' => $user->id,
                    'type' => 'adjustment',
                    'points' => bcmul($clawback, '-1', 3),
                    'balance_after' => $balanceAfter,
                ]);

                $customer->update(['points_balance' => $balanceAfter]);
            });
    }

    private function reversePromotions(Sale $sale): void
    {
        PromotionRedemption::where('sale_id', $sale->id)
            ->get()
            ->each(function (PromotionRedemption $redemption) {
                Promotion::whereKey($redemption->promotion_id)->decrement('redemption_count');

                if ($redemption->coupon_id !== null) {
                    Coupon::whereKey($redemption->coupon_id)->decrement('use_count');
                }

                $redemption->delete();
            });
    }

    private function magnitude(string $decimal): string
    {
        return bccomp($decimal, '0', 3) < 0 ? bcmul($decimal, '-1', 3) : $decimal;
    }
}
