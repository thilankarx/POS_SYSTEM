<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Giftcards\Actions\RecordGiftcardRedemptionAction;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Loyalty\Actions\AwardLoyaltyPointsAction;
use App\Domain\Loyalty\Actions\RecordPointsRedemptionAction;
use App\Domain\Promotions\Actions\RecordPromotionRedemptionsAction;
use App\Domain\Sales\CartPricer;
use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use App\Domain\Sales\Models\Shift;
use App\Support\Money\Money as MoneySupport;
use Illuminate\Support\Facades\DB;

/**
 * Turns a priced cart into a committed sale.
 *
 * The whole thing runs in one transaction. Stock rows are locked before they
 * are read so two registers cannot oversell the same unit, and everything that
 * is not strictly part of taking the money is deferred to the SaleCompleted
 * event.
 *
 * Compare Sale::save_value() in the legacy app: 180 lines that wrote sales,
 * payments, lines, the inventory ledger, taxes, gift-card balances, reward
 * points and dinner-table state inline, with unlocked read-then-write on the
 * balances.
 */
final class CompleteSaleAction
{
    public function __construct(
        private readonly CartPricer $pricer,
        private readonly InventoryService $inventory,
        private readonly DocumentNumberGenerator $numbers,
        private readonly RecordPromotionRedemptionsAction $redemptions,
        private readonly RecordPointsRedemptionAction $pointsRedemption,
        private readonly RecordGiftcardRedemptionAction $giftcardRedemption,
        private readonly AwardLoyaltyPointsAction $awardPoints,
    ) {}

    public function execute(Cart $cart, bool $enforceStock = true): Sale
    {
        return DB::transaction(function () use ($cart, $enforceStock) {
            // Locked and re-read, not the possibly-stale $cart the caller
            // passed in -- two concurrent completes of the same cart (a
            // double-tap that mints a fresh Idempotency-Key each time, or
            // two terminals both resuming the same cart) must not both see
            // "active" and both build a Sale. Everything below operates on
            // this locked row, not the argument.
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $cart->loadMissing(['lines.item', 'payments.method', 'customer']);

            if ($cart->status === Cart::STATUS_COMPLETED) {
                throw CheckoutException::cartNotActive($cart->status);
            }

            if ($cart->lines->isEmpty()) {
                throw CheckoutException::emptyCart();
            }

            // A return is only ever created by RefundSaleAction, which
            // builds its own Sale row (negated line-by-line against the
            // original sale) and never goes through this action.
            // CreateOrResumeCartRequest is the only place a Cart is created
            // and it refuses 'return' there -- but that is one caller's
            // guard, not a property of this method. Belt-and-braces: if a
            // Cart with sale_type=return ever reaches here by any other
            // path, refuse it here too, rather than silently completing it
            // as an ordinary sale that decrements stock like a return while
            // booking positive revenue like a POS sale.
            if ($cart->sale_type === Sale::TYPE_RETURN) {
                throw CheckoutException::cartNotActive($cart->sale_type);
            }

            // Locked (not the unlocked $cart->shift relation) so a
            // concurrent CloseShiftAction on the same shift either blocks
            // until this transaction commits, or has already committed and
            // is correctly seen as closed here -- rather than both this sale
            // and the close proceeding independently, unaware of each other,
            // leaving cash the close never counted.
            if ($cart->shift_id !== null) {
                $shift = Shift::query()->whereKey($cart->shift_id)->lockForUpdate()->firstOrFail();

                if (! $shift->isOpen()) {
                    throw CheckoutException::shiftClosed();
                }
            }

            $totals = $this->pricer->price($cart);

            // Quotes and work orders are commitments, not takings: they are
            // recorded without payment and without moving stock.
            $requiresPayment = in_array($cart->sale_type, [Cart::STATUS_ACTIVE, Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN], true)
                && ! in_array($cart->sale_type, [Sale::TYPE_QUOTE, Sale::TYPE_WORK_ORDER], true);

            $paid = MoneySupport::zero();
            foreach ($cart->payments as $payment) {
                $paid = $paid->plus(MoneySupport::of($payment->amount));
            }

            // A tip is money the customer is expected to have handed over too --
            // fold it into what "paid" must cover before checking for underpay,
            // same as the item/tax total.
            $tipAmount = MoneySupport::of($cart->tip_amount);
            $dueTotal = $totals->total->plus($tipAmount);

            if ($requiresPayment && $paid->isLessThan($dueTotal)) {
                throw CheckoutException::underpaid((string) $dueTotal->minus($paid)->getAmount());
            }

            // Change is the customer's overpayment. The register clamps each
            // payment's `amount` to the due total and records the cash
            // actually received in `tendered`, so the change normally lives
            // in that gap; a payment booked directly above due (no
            // `tendered`) shows up instead as paid exceeding the bill. Take
            // whichever is larger rather than summing, so the two views of
            // the same overpayment never double-count.
            $tenderedChange = MoneySupport::zero();
            foreach ($cart->payments as $payment) {
                if ($payment->tendered === null || ! ($payment->method?->allows_change ?? false)) {
                    continue;
                }

                $tendered = MoneySupport::of($payment->tendered);
                $amount = MoneySupport::of($payment->amount);

                if ($tendered->isGreaterThan($amount)) {
                    $tenderedChange = $tenderedChange->plus($tendered->minus($amount));
                }
            }

            $overpaid = $paid->isGreaterThan($dueTotal)
                ? $paid->minus($dueTotal)
                : MoneySupport::zero();

            $change = $tenderedChange->isGreaterThan($overpaid) ? $tenderedChange : $overpaid;

            $movesStock = $requiresPayment;

            if ($movesStock && $enforceStock) {
                $this->assertStockAvailable($cart);
                $this->assertSerialsAvailable($cart);
            }

            $lockedPromotions = $totals->appliedPromotions !== []
                ? $this->redemptions->verifyAndLock($cart, $totals)
                : [];

            $lockedCustomerForPoints = $this->pointsRedemption->verifyAndLock($cart);
            $lockedGiftcard = $this->giftcardRedemption->verifyAndLock($cart);

            // A quote/work order is a commitment, not a sale (no payment
            // ever changes hands unless/until it's rung up separately), so
            // it earns no commission -- everything else that reaches here
            // (pos/invoice/return) does, following Sale::scopeRevenue()'s
            // same type list. Commission is on the net-of-discount,
            // pre-tax amount, using the waiter's rate frozen at this
            // moment so a later rate change never rewrites history.
            $waiter = $cart->waiter_id !== null ? User::find($cart->waiter_id) : null;
            $commissionRate = in_array($cart->sale_type, [Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN], true)
                ? $waiter?->commission_rate
                : null;
            // $totals->subtotal is already net of every line discount (see
            // CartPricer::price()) -- it must not be discounted a second time
            // here, or commission ends up computed on gross-minus-2x-discount
            // and goes negative past a 50% line discount.
            $commissionAmount = $commissionRate !== null
                ? MoneySupport::percentageOf($totals->subtotal, (string) $commissionRate)
                : MoneySupport::zero();

            $sale = Sale::create([
                'number' => $this->numbers->next('sale'),
                'client_uuid' => $cart->client_uuid,
                'customer_id' => $cart->customer_id,
                'user_id' => $cart->user_id,
                'waiter_id' => $cart->waiter_id,
                'commission_rate_applied' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'terminal_id' => $cart->terminal_id,
                'shift_id' => $cart->shift_id,
                'stock_location_id' => $cart->stock_location_id,
                'dinner_table_id' => $cart->dinner_table_id,
                'sale_type' => $cart->sale_type,
                'status' => Sale::STATUS_COMPLETED,
                'invoice_number' => $cart->sale_type === Sale::TYPE_INVOICE ? $this->numbers->next('invoice') : null,
                'quote_number' => $cart->sale_type === Sale::TYPE_QUOTE ? $this->numbers->next('quote') : null,
                'work_order_number' => $cart->sale_type === Sale::TYPE_WORK_ORDER ? $this->numbers->next('work_order') : null,
                'subtotal' => $totals->subtotal,
                'discount_total' => $totals->discountTotal,
                'tax_total' => $totals->taxTotal,
                'rounding_adjustment' => $totals->roundingAdjustment,
                'total' => $totals->total,
                'tip_amount' => $tipAmount,
                'paid_total' => $paid,
                'change_given' => $change,
                'cost_total' => $totals->costTotal,
                'currency' => MoneySupport::currency(),
                'comment' => $cart->comment,
                'sold_at' => now(),
            ]);

            foreach ($cart->lines as $cartLine) {
                $priced = $totals->lineFor((int) $cartLine->line_number);

                $saleLine = SaleLine::create([
                    'sale_id' => $sale->id,
                    'line_number' => $cartLine->line_number,
                    'item_id' => $cartLine->item_id,
                    'item_kit_id' => $cartLine->item_kit_id,
                    'stock_lot_id' => $cartLine->stock_lot_id,
                    'stock_location_id' => $cartLine->stock_location_id,
                    // Snapshot: a later catalog edit must not rewrite history.
                    'item_name' => $cartLine->item->name,
                    'sku' => $cartLine->item->sku,
                    'description' => $cartLine->description,
                    'serial' => $cartLine->serial,
                    'quantity' => $cartLine->quantity,
                    'unit_price' => $cartLine->unit_price,
                    'cost_price' => $cartLine->cost_price,
                    'discount_value' => $cartLine->discount_value,
                    'discount_type' => $cartLine->discount_type,
                    'discount_amount' => $priced?->discount,
                    'line_subtotal' => $priced?->net,
                    'line_tax' => $priced?->tax,
                    'line_total' => $priced?->total,
                    'price_overridden' => $cartLine->price_overridden,
                    'price_overridden_by_user_id' => $cartLine->price_overridden_by_user_id,
                ]);

                // Logged explicitly, not via LogsActivity's automatic
                // dirty-tracking: SaleLine rows are create-only, and the
                // overwhelming majority are never overridden, so an
                // unconditional log-on-create would flood the activity log
                // with one row per ordinary line ever sold. This fires only
                // for the (rare) overridden ones, which is what an auditor
                // actually needs to find.
                if ($cartLine->price_overridden) {
                    activity()
                        ->causedBy($cartLine->price_overridden_by_user_id !== null
                            ? User::find($cartLine->price_overridden_by_user_id)
                            : null)
                        ->performedOn($saleLine)
                        ->withProperties(['unit_price' => (string) MoneySupport::of($saleLine->unit_price)->getAmount()])
                        ->log('price overridden');
                }

                foreach ($totals->taxResult->forLine((int) $cartLine->line_number) as $tax) {
                    $saleLine->taxes()->create([
                        'tax_rate_id' => $tax->taxRateId,
                        'name' => $tax->name,
                        'rate' => $tax->rate,
                        'taxable_amount' => $tax->taxableAmount,
                        'tax_amount' => $tax->taxAmount,
                        'is_inclusive' => $tax->isInclusive,
                    ]);
                }

                if ($movesStock) {
                    $this->inventory->record(
                        item: $cartLine->item,
                        stockLocationId: (int) $cartLine->stock_location_id,
                        quantityDelta: bcmul((string) $cartLine->quantity, '-1', 3),
                        reason: $sale->sale_type === Sale::TYPE_RETURN
                            ? StockMovement::REASON_RETURN
                            : StockMovement::REASON_SALE,
                        source: $sale,
                        userId: (int) $cart->user_id,
                        stockLotId: $cartLine->stock_lot_id,
                        unitCost: (string) MoneySupport::of($cartLine->cost_price)->getAmount(),
                    );

                    // Only the forward-sale path: a return's effect on a
                    // previously-sold serial isn't handled by this pass (see
                    // assertSerialsAvailable()'s docblock).
                    if ($cartLine->serial !== null && $sale->sale_type !== Sale::TYPE_RETURN) {
                        SerialNumber::where('item_id', $cartLine->item_id)
                            ->where('serial', $cartLine->serial)
                            ->update(['status' => SerialNumber::STATUS_SOLD, 'sold_on_sale_id' => $sale->id]);
                    }
                }
            }

            foreach ($totals->taxResult->summary as $index => $tax) {
                $sale->taxes()->create([
                    'tax_rate_id' => $tax->taxRateId,
                    'name' => $tax->name,
                    'rate' => $tax->rate,
                    'taxable_amount' => $tax->taxableAmount,
                    'tax_amount' => $tax->taxAmount,
                    'print_sequence' => $index,
                ]);
            }

            if ($lockedPromotions !== []) {
                $this->redemptions->commit($sale, $cart, $lockedPromotions, $totals);
            }

            $this->pointsRedemption->commit($sale, $cart, $lockedCustomerForPoints);
            $this->giftcardRedemption->commit($sale, $cart, $lockedGiftcard);

            foreach ($cart->payments as $cartPayment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => $cartPayment->payment_method_id,
                    'user_id' => $cart->user_id,
                    'amount' => $cartPayment->amount,
                    'tendered' => $cartPayment->tendered,
                    'change_given' => MoneySupport::zero(),
                    'currency' => MoneySupport::currency(),
                    'provider' => $cartPayment->method?->provider ?? 'manual',
                    'status' => Payment::STATUS_CAPTURED,
                    'reference' => $cartPayment->reference,
                    'captured_at' => now(),
                ]);
            }

            $cart->update(['status' => Cart::STATUS_COMPLETED]);

            if ($cart->dinner_table_id !== null) {
                DinnerTable::where('id', $cart->dinner_table_id)->update(['status' => DinnerTable::STATUS_AVAILABLE]);
            }

            $this->awardPoints->execute($sale);

            SaleCompleted::dispatch($sale);

            return $sale->refresh();
        });
    }

    /**
     * Lock the stock rows for every stocked line, then verify availability.
     * Locking before reading is what stops two concurrent sales from each
     * seeing the same last unit.
     */
    private function assertStockAvailable(Cart $cart): void
    {
        // Grouped by (item_id, stock_location_id): two lines of the same
        // item in one cart (a re-ordered dish, a different lot) must be
        // checked against their *combined* demand -- otherwise, with one
        // unit in stock and two lines of quantity 1, each line individually
        // sees "1 available >= 1 needed" against the same unread-back
        // quantity and both pass, overselling with no concurrency involved
        // at all. Locked in a canonical key order (not cart-entry order) so
        // two terminals selling the same two items in opposite order don't
        // deadlock each other.
        $demand = [];
        $lineByKey = [];

        foreach ($cart->lines as $line) {
            if (! $line->item->movesStock()) {
                continue;
            }

            $key = $line->item_id.':'.$line->stock_location_id;
            $demand[$key] = bcadd($demand[$key] ?? '0', (string) $line->quantity, 3);
            $lineByKey[$key] ??= $line;
        }

        ksort($demand);

        foreach ($demand as $key => $quantity) {
            $line = $lineByKey[$key];

            $available = (string) (DB::table('stock_levels')
                ->where('item_id', $line->item_id)
                ->where('stock_location_id', $line->stock_location_id)
                ->lockForUpdate()
                ->value('quantity') ?? '0');

            if (bccomp($available, $quantity, 3) < 0) {
                throw CheckoutException::insufficientStock($line->item->name, $available);
            }
        }
    }

    /**
     * Lock and verify every serialized line's serial before anything is
     * written -- same reasoning as assertStockAvailable(): two registers
     * completing against the same physical serial at once must not both
     * succeed. Only the forward-sale path (POS/invoice/quote/work order);
     * a return's effect on a previously-sold serial isn't handled by this
     * pass -- a returned item's SerialNumber is left `sold`.
     */
    private function assertSerialsAvailable(Cart $cart): void
    {
        if ($cart->sale_type === Sale::TYPE_RETURN) {
            return;
        }

        foreach ($cart->lines as $line) {
            if (! $line->item->is_serialized) {
                continue;
            }

            if ($line->serial === null) {
                throw CheckoutException::serialRequired($line->item->name);
            }

            $serial = SerialNumber::where('item_id', $line->item_id)
                ->where('serial', $line->serial)
                ->lockForUpdate()
                ->first();

            if ($serial === null || $serial->status !== SerialNumber::STATUS_IN_STOCK) {
                throw CheckoutException::serialNotAvailable($line->serial);
            }
        }
    }
}
