<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Settings\BusinessProfileSettings;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

final class AddCartLineAction
{
    public function execute(Cart $cart, Item $item, string $quantity, ?int $stockLotId = null, ?string $description = null): CartLine
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw CheckoutException::cartNotActive($cart->status);
        }

        // ItemController::index already filters the catalog the register
        // searches against, but that's a listing filter, not enforcement --
        // POST /carts/{cart}/lines never checked this at all, so a
        // filtered-out item's id could still be added directly by number.
        if (! $item->soldByBusinessType(app(BusinessProfileSettings::class)->business_type)) {
            throw CheckoutException::itemNotSoldHere($item->name);
        }

        // A caller-supplied lot always wins -- but only if it is actually a
        // lot of *this* item. The request only validates that the id exists
        // in stock_lots at all (Rule::exists), so without this check a
        // queued offline op (or a direct API call) naming another item's lot
        // id would tag this line's stock movement to it, corrupting that
        // other item's lot ledger and expiry tracking.
        if ($stockLotId !== null && ! StockLot::where('id', $stockLotId)->where('item_id', $item->id)->exists()) {
            throw CheckoutException::lotDoesNotMatchItem($item->name);
        }

        // Every stocked item now always prices from a lot -- explicit, or
        // the oldest-to-expire in-stock one (FEFO). A service/amount-entry
        // item never has lots at all and prices from its own unit_price
        // instead. Lot tracking is no longer opt-in: an item with no lots
        // yet (never received) or a lot missing a price cannot be sold,
        // rather than silently falling back to some other number.
        $stockLot = null;
        if ($item->movesStock()) {
            $stockLot = $stockLotId !== null
                ? StockLot::find($stockLotId)
                : StockLot::where('item_id', $item->id)->fefo()->first();

            if ($stockLot === null || $stockLot->selling_price === null || $stockLot->cost_price === null) {
                throw CheckoutException::itemHasNoPricedStock($item->name);
            }
        }
        $stockLotId = $stockLot?->id;
        $unitPrice = $item->movesStock() ? $stockLot->selling_price : $item->unit_price;
        $costPrice = $item->movesStock() ? $stockLot->cost_price : Money::zero();

        return DB::transaction(function () use ($cart, $item, $quantity, $stockLotId, $unitPrice, $costPrice, $description) {
            // A line the kitchen has already prepared is excluded from the
            // merge match on purpose: bumping its quantity in place would
            // make a *closed* ticket silently read as a bigger order than
            // what was actually fired, instead of the kitchen seeing a
            // clean new ticket for just the extra units. Re-ordering the
            // same dish after it's done falls through to create a fresh
            // line below.
            $existing = $cart->lines()
                ->where('item_id', $item->id)
                ->whereNull('item_kit_id')
                ->where('stock_lot_id', $stockLotId)
                ->whereNull('kitchen_prepared_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                // Merging more quantity into a line already fired to the
                // kitchen (but not yet prepared) is exactly the kind of
                // change the kitchen needs to know about -- reset it back
                // to unsent so the next "Send to kitchen" picks up the
                // extra units instead of the cart silently reading as
                // fully sent.
                $existing->update([
                    'quantity' => bcadd((string) $existing->quantity, $quantity, 3),
                    'kitchen_sent_at' => null,
                ]);

                return $existing->refresh();
            }

            return CartLine::create([
                'cart_id' => $cart->id,
                'line_number' => $cart->nextLineNumber(),
                'item_id' => $item->id,
                'stock_lot_id' => $stockLotId,
                'stock_location_id' => $cart->stock_location_id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'discount_value' => 0,
                'discount_type' => 'percent',
                'price_overridden' => false,
            ]);
        });
    }
}
