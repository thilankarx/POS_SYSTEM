<?php

declare(strict_types=1);

namespace App\Domain\Giftcards\Actions;

use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Giftcards\Models\GiftcardTransaction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\Sale;

/**
 * Same two-phase shape as RecordPromotionRedemptionsAction /
 * RecordPointsRedemptionAction: an optimistic check happens at
 * AddCartPaymentAction time, but the authoritative, race-safe debit only
 * ever happens here, under a row lock, inside CompleteSaleAction's
 * transaction.
 */
final class RecordGiftcardRedemptionAction
{
    public function verifyAndLock(Cart $cart): ?Giftcard
    {
        $giftcardPayment = $cart->payments->first(fn ($payment) => $payment->method?->code === 'giftcard');

        if ($giftcardPayment === null) {
            return null;
        }

        $giftcard = Giftcard::query()->lockForUpdate()->where('number', $giftcardPayment->reference)->first();

        if ($giftcard === null) {
            throw GiftcardException::notFound();
        }

        if (! $giftcard->is_active) {
            throw GiftcardException::inactive();
        }

        if ($giftcard->expires_at !== null && $giftcard->expires_at->isPast()) {
            throw GiftcardException::expired();
        }

        if ($giftcard->balance->isLessThan($giftcardPayment->amount)) {
            throw GiftcardException::insufficientBalance();
        }

        return $giftcard;
    }

    public function commit(Sale $sale, Cart $cart, ?Giftcard $lockedGiftcard): void
    {
        if ($lockedGiftcard === null) {
            return;
        }

        $giftcardPayment = $cart->payments->first(fn ($payment) => $payment->method?->code === 'giftcard');
        $balanceAfter = $lockedGiftcard->balance->minus($giftcardPayment->amount);

        GiftcardTransaction::create([
            'giftcard_id' => $lockedGiftcard->id,
            'sale_id' => $sale->id,
            'user_id' => $sale->user_id,
            'type' => 'redeem',
            'amount' => $giftcardPayment->amount->negated(),
            'balance_after' => $balanceAfter,
        ]);

        $lockedGiftcard->update(['balance' => $balanceAfter]);
    }
}
