<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\ReturnReason;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records a return against a completed sale as its own Sale row
 * (sale_type: return, linked back via returns_sale_id) rather than mutating
 * the original -- see Sale::getActivitylogOptions()'s "refunds must be
 * attributable" comment, which is the schema's own reason for shaping it
 * this way.
 *
 * Every money field on the return sale is negative. Reports never
 * special-case sale_type: SalesReportQuery sums subtotal/tax_total/total
 * straight across Sale::revenue() (pos+invoice+return), so a negative
 * return is what makes that sum come out correct with no report changes.
 */
final class RefundSaleAction
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DocumentNumberGenerator $numbers,
        private readonly ReverseSaleRedemptionsAction $reverseRedemptions,
    ) {}

    /**
     * @param  array<int, string>  $lineQuantities  [sale_line_id => quantity to return]
     */
    public function execute(
        Sale $sale,
        array $lineQuantities,
        int $returnReasonId,
        int $refundPaymentMethodId,
        User $user,
    ): Sale {
        return DB::transaction(function () use ($sale, $lineQuantities, $returnReasonId, $refundPaymentMethodId, $user) {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! in_array($sale->status, [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_REFUNDED], true)) {
                throw CheckoutException::saleNotRefundable($sale->status);
            }

            $reason = ReturnReason::findOrFail($returnReasonId);

            $lines = SaleLine::where('sale_id', $sale->id)
                ->whereIn('id', array_keys($lineQuantities))
                ->with(['item', 'taxes'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $prepared = $this->prepareReturnLines($lines, $lineQuantities);

            if ($prepared === []) {
                throw CheckoutException::noReturnLinesSelected();
            }

            $returnTotal = array_reduce($prepared, fn (Money $carry, array $p) => $carry->plus($p['total']), MoneySupport::zero());
            $alreadyRefunded = MoneySupport::of((string) ($sale->returns()->sum('total') ?: '0'))->abs();

            if ($alreadyRefunded->plus($returnTotal->abs())->isGreaterThan($sale->total)) {
                throw CheckoutException::refundExceedsSaleTotal();
            }

            $returnSubtotal = array_reduce($prepared, fn (Money $c, array $p) => $c->plus($p['subtotal']), MoneySupport::zero());
            $returnDiscount = array_reduce($prepared, fn (Money $c, array $p) => $c->plus($p['discount']), MoneySupport::zero());

            // The original sale's frozen rate, not a fresh lookup against
            // the waiter's current rate -- so this reversal always exactly
            // nets against what was originally credited, regardless of any
            // rate change in between. $returnSubtotal is already net of the
            // line's own discount (it is prorated from the forward sale's
            // stored, already-net line_subtotal -- see CompleteSaleAction),
            // and already negative (see the class docblock), so it must not
            // be discounted a second time: same basis CompleteSaleAction uses
            // forward, so the reversal exactly cancels the original.
            $returnCommission = $sale->commission_rate_applied !== null
                ? MoneySupport::percentageOf($returnSubtotal, (string) $sale->commission_rate_applied)
                : MoneySupport::zero();

            $returnSale = Sale::create([
                'number' => $this->numbers->next('sale'),
                'customer_id' => $sale->customer_id,
                'user_id' => $user->id,
                'waiter_id' => $sale->waiter_id,
                'commission_rate_applied' => $sale->commission_rate_applied,
                'commission_amount' => $returnCommission,
                // KNOWN GAP, not fixed here: refunds are a back-office-only
                // flow (routes/backoffice.php) with no terminal/shift
                // context collected from the operator, so a cash refund
                // never reduces any shift's expected_cash even though real
                // money physically leaves a drawer. Closing this needs a
                // product decision on which terminal's drawer a back-office
                // refund is presumed to draw from (or a UI change to ask),
                // not a guess made silently here.
                'terminal_id' => null,
                'shift_id' => null,
                'stock_location_id' => $sale->stock_location_id,
                'sale_type' => Sale::TYPE_RETURN,
                'status' => Sale::STATUS_COMPLETED,
                'returns_sale_id' => $sale->id,
                'return_reason_id' => $reason->id,
                'subtotal' => $returnSubtotal,
                'discount_total' => $returnDiscount,
                'tax_total' => array_reduce($prepared, fn (Money $c, array $p) => $c->plus($p['tax']), MoneySupport::zero()),
                'rounding_adjustment' => MoneySupport::zero(),
                'total' => $returnTotal,
                'paid_total' => $returnTotal,
                'change_given' => MoneySupport::zero(),
                'cost_total' => array_reduce($prepared, fn (Money $c, array $p) => $c->plus($p['cost']), MoneySupport::zero()),
                'currency' => MoneySupport::currency(),
                'sold_at' => now(),
            ]);

            $taxByRate = [];

            foreach ($prepared as $lineNumber => $p) {
                $originalLine = $p['line'];

                $returnLine = SaleLine::create([
                    'sale_id' => $returnSale->id,
                    'line_number' => $lineNumber + 1,
                    'item_id' => $originalLine->item_id,
                    'stock_lot_id' => $originalLine->stock_lot_id,
                    'stock_location_id' => $originalLine->stock_location_id,
                    'item_name' => $originalLine->item_name,
                    'sku' => $originalLine->sku,
                    'quantity' => $p['quantity'],
                    'unit_price' => $originalLine->unit_price,
                    'cost_price' => $originalLine->cost_price,
                    'discount_amount' => $p['discount'],
                    'line_subtotal' => $p['subtotal'],
                    'line_tax' => $p['tax'],
                    'line_total' => $p['total'],
                    'price_overridden' => $originalLine->price_overridden,
                    'price_overridden_by_user_id' => $originalLine->price_overridden_by_user_id,
                ]);

                foreach ($p['taxes'] as $tax) {
                    $returnLine->taxes()->create($tax);

                    $key = $tax['tax_rate_id'] ?? 'none';
                    $taxByRate[$key] ??= ['tax_rate_id' => $tax['tax_rate_id'], 'name' => $tax['name'], 'rate' => $tax['rate'], 'taxable_amount' => MoneySupport::zero(), 'tax_amount' => MoneySupport::zero()];
                    $taxByRate[$key]['taxable_amount'] = $taxByRate[$key]['taxable_amount']->plus($tax['taxable_amount']);
                    $taxByRate[$key]['tax_amount'] = $taxByRate[$key]['tax_amount']->plus($tax['tax_amount']);
                }

                $originalLine->update([
                    'quantity_returned' => bcadd((string) $originalLine->quantity_returned, $p['quantity'], 3),
                ]);

                if ($reason->restocks && $originalLine->item->movesStock()) {
                    $this->inventory->record(
                        item: $originalLine->item,
                        stockLocationId: (int) $originalLine->stock_location_id,
                        quantityDelta: $p['quantity'],
                        reason: StockMovement::REASON_RETURN,
                        source: $returnSale,
                        userId: $user->id,
                        stockLotId: $originalLine->stock_lot_id,
                        unitCost: (string) MoneySupport::of($originalLine->cost_price)->getAmount(),
                    );
                }
            }

            foreach (array_values($taxByRate) as $index => $tax) {
                $returnSale->taxes()->create([
                    'tax_rate_id' => $tax['tax_rate_id'],
                    'name' => $tax['name'],
                    'rate' => $tax['rate'],
                    'taxable_amount' => $tax['taxable_amount'],
                    'tax_amount' => $tax['tax_amount'],
                    'print_sequence' => $index,
                ]);
            }

            $refundsPaymentId = $sale->payments()
                ->where('payment_method_id', $refundPaymentMethodId)
                ->pluck('id');

            Payment::create([
                'sale_id' => $returnSale->id,
                'payment_method_id' => $refundPaymentMethodId,
                'user_id' => $user->id,
                'amount' => $returnTotal,
                'tendered' => MoneySupport::zero(),
                'change_given' => MoneySupport::zero(),
                'currency' => MoneySupport::currency(),
                'provider' => 'manual',
                'status' => Payment::STATUS_REFUNDED,
                'refunds_payment_id' => $refundsPaymentId->count() === 1 ? $refundsPaymentId->first() : null,
                'captured_at' => now(),
            ]);

            // Gated to the method the operator explicitly chose to refund
            // through: crediting the gift card/points back regardless of
            // that choice would double the customer's money on a
            // split-tender sale refunded via a different method. Earned
            // points are clawed back unconditionally (see
            // ReverseSaleRedemptionsAction) since that has no such
            // ambiguity, and promotion counters are left untouched here --
            // which *of several* redemption slots a partial-quantity return
            // should release is not well-defined, unlike a full void.
            $refundMethod = PaymentMethod::find($refundPaymentMethodId);
            $saleTotalAmount = (string) $sale->total->getAmount();
            $share = bccomp($saleTotalAmount, '0', 6) > 0
                ? bcdiv((string) $returnTotal->abs()->getAmount(), $saleTotalAmount, 6)
                : '0';

            $this->reverseRedemptions->execute(
                sale: $sale,
                share: $share,
                user: $user,
                reverseGiftcard: $refundMethod?->code === 'giftcard',
                reversePoints: $refundMethod?->code === 'points',
                reversePromotions: false,
            );

            $stillReturnable = $sale->lines()->get()->contains(fn (SaleLine $line) => bccomp($line->remainingReturnable(), '0', 3) > 0);

            $sale->update([
                'status' => $stillReturnable ? Sale::STATUS_PARTIALLY_REFUNDED : Sale::STATUS_REFUNDED,
            ]);

            return $returnSale->fresh(['lines', 'payments', 'taxes']);
        });
    }

    /**
     * @param  Collection<int, SaleLine>  $lines
     * @param  array<int, string>  $lineQuantities
     * @return list<array{line: SaleLine, quantity: string, subtotal: Money, discount: Money, tax: Money, total: Money, cost: Money, taxes: list<array<string, mixed>>}>
     */
    private function prepareReturnLines($lines, array $lineQuantities): array
    {
        $prepared = [];

        foreach ($lineQuantities as $saleLineId => $quantity) {
            if (bccomp($quantity, '0', 3) <= 0) {
                continue;
            }

            $line = $lines->get($saleLineId);

            if ($line === null) {
                continue;
            }

            if (bccomp($quantity, $line->remainingReturnable(), 3) > 0) {
                throw CheckoutException::returnExceedsRemaining($line->item_name);
            }

            // Multiply first, then divide, so a full-quantity return (the
            // common case) comes back to exactly the original line value
            // instead of rounding the per-unit share to the nearest cent
            // before scaling it back up -- that used to make a full return
            // either exceed the sale total (blocking the refund outright) or
            // land a cent short of it.
            $portion = fn (Money $value): Money => $value
                ->multipliedBy($quantity, RoundingMode::HalfUp)
                ->dividedBy((string) $line->quantity, RoundingMode::HalfUp);

            $subtotal = $portion(MoneySupport::of($line->line_subtotal))->negated();
            $discount = $portion(MoneySupport::of($line->discount_amount))->negated();
            $tax = $portion(MoneySupport::of($line->line_tax))->negated();
            $total = $portion(MoneySupport::of($line->line_total))->negated();
            $cost = MoneySupport::of($line->cost_price)->multipliedBy($quantity, RoundingMode::HalfUp)->negated();

            $taxes = $line->taxes->map(function ($lineTax) use ($portion) {
                return [
                    'tax_rate_id' => $lineTax->tax_rate_id,
                    'name' => $lineTax->name,
                    'rate' => $lineTax->rate,
                    'taxable_amount' => $portion(MoneySupport::of($lineTax->taxable_amount))->negated(),
                    'tax_amount' => $portion(MoneySupport::of($lineTax->tax_amount))->negated(),
                    'is_inclusive' => $lineTax->is_inclusive,
                ];
            })->all();

            $prepared[] = [
                'line' => $line,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'cost' => $cost,
                'taxes' => $taxes,
            ];
        }

        return $prepared;
    }
}
