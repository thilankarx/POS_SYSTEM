<?php

declare(strict_types=1);

namespace App\Domain\Giftcards\Actions;

use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Giftcards\Models\GiftcardTransaction;
use App\Domain\Identity\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

final class TopUpGiftcardAction
{
    public function execute(Giftcard $giftcard, string $amount, User $user): GiftcardTransaction
    {
        if (! $giftcard->is_active) {
            throw GiftcardException::inactive();
        }

        if ($giftcard->expires_at !== null && $giftcard->expires_at->isPast()) {
            throw GiftcardException::expired();
        }

        return DB::transaction(function () use ($giftcard, $amount, $user) {
            $locked = Giftcard::query()->lockForUpdate()->findOrFail($giftcard->id);
            $balanceAfter = $locked->balance->plus(Money::of($amount));

            $transaction = GiftcardTransaction::create([
                'giftcard_id' => $locked->id,
                'user_id' => $user->id,
                'type' => 'topup',
                'amount' => $amount,
                'balance_after' => $balanceAfter,
            ]);

            $locked->update(['balance' => $balanceAfter]);

            return $transaction;
        });
    }
}
