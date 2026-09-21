<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\CartLine;

final class UpdateCartLineAction
{
    /**
     * @param  array<string, mixed>  $attributes  validated fields to change; only keys present are applied
     */
    public function execute(CartLine $line, array $attributes, User $user): CartLine
    {
        $updates = array_intersect_key($attributes, array_flip([
            'quantity', 'unit_price', 'discount_value', 'discount_type', 'description', 'serial',
        ]));

        if (array_key_exists('unit_price', $attributes)) {
            $updates['price_overridden'] = true;
            $updates['price_overridden_by_user_id'] = $user->id;
        }

        // Bumping the quantity of a line the kitchen already prepared in
        // place would make a *closed* ticket silently read as a bigger
        // order than what was actually fired -- refuse it and point the
        // cashier at re-ordering the dish instead (which starts a clean
        // new line and ticket for just the extra units). A decrease is a
        // correction, not a new order, and stays allowed.
        if (array_key_exists('quantity', $updates)
            && $line->kitchen_prepared_at !== null
            && bccomp((string) $updates['quantity'], (string) $line->quantity, 3) > 0) {
            throw CheckoutException::cannotIncreasePreparedLineQuantity();
        }

        // A quantity or note change on a line already fired to the kitchen
        // but not yet prepared is exactly the kind of change the kitchen
        // needs to know about -- reset it back to unsent so the next "Send
        // to kitchen" picks it up again, instead of silently discarding
        // the change. Once a line is prepared, only a quantity *increase*
        // would need to notify the kitchen again, and that's refused
        // above; a decrease or a note edit on already-prepared food
        // doesn't need to resurrect the ticket. Price/discount/serial
        // edits never concern the kitchen.
        if ($line->kitchen_sent_at !== null && $line->kitchen_prepared_at === null
            && (array_key_exists('quantity', $updates) || array_key_exists('description', $updates))) {
            $updates['kitchen_sent_at'] = null;
        }

        if ($updates !== []) {
            $line->update($updates);
        }

        return $line->refresh();
    }
}
