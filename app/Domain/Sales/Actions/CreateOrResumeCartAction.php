<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Facades\DB;

final class CreateOrResumeCartAction
{
    public function execute(
        User $user,
        Terminal $terminal,
        string $clientUuid,
        ?int $customerId = null,
        string $saleType = Sale::TYPE_POS,
        ?int $dinnerTableId = null,
        ?string $reference = null,
        ?string $comment = null,
    ): Cart {
        if (! $user->canOperateAt($terminal->stock_location_id)) {
            throw ShiftException::unauthorizedTerminal();
        }

        return DB::transaction(function () use ($terminal, $user, $clientUuid, $customerId, $saleType, $dinnerTableId, $reference, $comment) {
            $cart = Cart::where('client_uuid', $clientUuid)->lockForUpdate()->first();

            if ($cart !== null) {
                if (! in_array($cart->status, [Cart::STATUS_ACTIVE, Cart::STATUS_SUSPENDED], true)) {
                    throw CheckoutException::cartNotActive($cart->status);
                }

                if ($cart->status === Cart::STATUS_SUSPENDED) {
                    if ($cart->stock_location_id !== $terminal->stock_location_id) {
                        throw ShiftException::unauthorizedTerminal();
                    }

                    // Whoever resumes a parked cart is who actually completes the
                    // sale -- reattribute terminal/shift/user to them so cash
                    // reconciliation and till reports credit the right shift, not
                    // whichever terminal happened to park it (which may by now
                    // belong to a closed shift entirely).
                    $shift = $terminal->openShift();

                    if ($shift === null) {
                        throw CheckoutException::shiftClosed();
                    }

                    $cart->update([
                        'status' => Cart::STATUS_ACTIVE,
                        'terminal_id' => $terminal->id,
                        'shift_id' => $shift->id,
                        'user_id' => $user->id,
                        'suspended_by_user_id' => null,
                        'suspended_at' => null,
                    ]);
                }

                return $cart;
            }

            $shift = $terminal->openShift();

            if ($shift === null) {
                throw CheckoutException::shiftClosed();
            }

            if ($dinnerTableId !== null) {
                $table = DinnerTable::where('id', $dinnerTableId)->lockForUpdate()->firstOrFail();

                if ($table->status !== DinnerTable::STATUS_AVAILABLE) {
                    throw CheckoutException::tableOccupied($table->name);
                }

                $table->update(['status' => DinnerTable::STATUS_OCCUPIED]);
            }

            return Cart::create([
                'client_uuid' => $clientUuid,
                'terminal_id' => $terminal->id,
                'shift_id' => $shift->id,
                'stock_location_id' => $terminal->stock_location_id,
                'user_id' => $user->id,
                // Whoever takes the order -- set once, here, and never
                // reattributed on resume like user_id is (see the comment
                // above): this is what a commission is actually credited
                // against.
                'waiter_id' => $user->id,
                'customer_id' => $customerId,
                'dinner_table_id' => $dinnerTableId,
                'sale_type' => $saleType,
                'status' => Cart::STATUS_ACTIVE,
                'reference' => $reference,
                'comment' => $comment,
                // Explicit rather than relying on the column's DB-level
                // default -- Eloquent never re-reads a default back onto a
                // freshly created in-memory model, so CartResource would see
                // a null tip_amount on this exact object until the next
                // reload.
                'tip_amount' => '0',
            ]);
        });
    }
}
