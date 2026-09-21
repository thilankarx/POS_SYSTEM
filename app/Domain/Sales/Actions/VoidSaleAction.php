<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use App\Support\Money\Money as MoneySupport;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a completed sale in place: same-day cancellation of a sale that
 * never should have completed, as opposed to RefundSaleAction, which records
 * a settlement adjustment as its own linked Sale row. No new Sale or Payment
 * row is created here -- stock is restored and the existing payments are
 * marked voided so CloseShiftAction::expectedCash() (which only counts
 * Payment::STATUS_CAPTURED) stops counting them automatically.
 */
final class VoidSaleAction
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ReverseSaleRedemptionsAction $reverseRedemptions,
    ) {}

    public function execute(Sale $sale, string $reason, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $user) {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== Sale::STATUS_COMPLETED) {
                throw CheckoutException::saleNotVoidable($sale->status);
            }

            $reason = trim($reason);

            if ($reason === '') {
                throw CheckoutException::voidReasonRequired();
            }

            $lines = SaleLine::where('sale_id', $sale->id)
                ->with('item')
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                if (! $line->item->movesStock()) {
                    continue;
                }

                $this->inventory->record(
                    item: $line->item,
                    stockLocationId: (int) $line->stock_location_id,
                    quantityDelta: (string) $line->quantity,
                    reason: StockMovement::REASON_VOID,
                    source: $sale,
                    userId: $user->id,
                    stockLotId: $line->stock_lot_id,
                    unitCost: (string) MoneySupport::of($line->cost_price)->getAmount(),
                );
            }

            $sale->payments()->update(['status' => Payment::STATUS_VOIDED]);

            // A void undoes the whole sale, so every store-value effect it
            // had is undone too -- unlike a refund, there's no "which method
            // did the operator pick" ambiguity to gate this behind.
            $this->reverseRedemptions->execute(
                sale: $sale,
                share: '1',
                user: $user,
                reverseGiftcard: true,
                reversePoints: true,
                reversePromotions: true,
            );

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'void_reason' => $reason,
                'voided_by_user_id' => $user->id,
                'voided_at' => now(),
            ]);

            return $sale->fresh(['lines', 'payments']);
        });
    }
}
