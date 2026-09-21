<?php

declare(strict_types=1);

namespace App\Domain\Giftcards\Actions;

use App\Domain\Crm\Models\Customer;
use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Giftcards\Models\GiftcardTransaction;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

final class IssueGiftcardAction
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function execute(string $initialValue, ?Customer $customer = null, ?string $expiresAt = null): Giftcard
    {
        return DB::transaction(function () use ($initialValue, $customer, $expiresAt) {
            $giftcard = Giftcard::create([
                'number' => $this->numbers->next('gift_card'),
                'customer_id' => $customer?->id,
                'initial_value' => $initialValue,
                'balance' => $initialValue,
                'currency' => Money::currency(),
                'is_active' => true,
                'expires_at' => $expiresAt,
            ]);

            GiftcardTransaction::create([
                'giftcard_id' => $giftcard->id,
                'type' => 'issue',
                'amount' => $initialValue,
                'balance_after' => $initialValue,
            ]);

            return $giftcard;
        });
    }
}
