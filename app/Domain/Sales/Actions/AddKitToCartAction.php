<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Explodes a kit into one CartLine per component, tagged with
 * item_kit_id -- schema and pricing already treat a kit line as an
 * ordinary line (CartPricer, stock deduction, CompleteSaleAction all
 * work purely off item_id), so the only real work here is deciding each
 * component's per-line discount so the group's total realizes the kit's
 * own discount_value/discount_type.
 */
final class AddKitToCartAction
{
    public function execute(Cart $cart, ItemKit $kit, string $kitQuantity = '1'): Collection
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw CheckoutException::cartNotActive($cart->status);
        }

        return DB::transaction(function () use ($cart, $kit, $kitQuantity) {
            $components = $kit->items()->get();

            $parts = [];
            $totalGross = MoneySupport::zero();

            foreach ($components as $component) {
                $quantity = bcmul((string) $component->pivot->quantity, $kitQuantity, 3);

                // Every stocked component now always prices from a lot --
                // transparent, oldest-to-expire (FEFO), no cashier
                // interaction (a kit component never gets the cart-line
                // price picker). A non-stocked component has no lots at
                // all and prices from its own unit_price instead.
                $stockLot = null;
                if ($component->movesStock()) {
                    $stockLot = StockLot::where('item_id', $component->id)->fefo()->first();

                    if ($stockLot === null || $stockLot->selling_price === null || $stockLot->cost_price === null) {
                        throw CheckoutException::itemHasNoPricedStock($component->name);
                    }
                }
                $unitPrice = $component->movesStock() ? $stockLot->selling_price : $component->unit_price;
                $costPrice = $component->movesStock() ? $stockLot->cost_price : MoneySupport::zero();

                $gross = $unitPrice->multipliedBy($quantity, RoundingMode::HalfUp);
                $parts[] = [
                    'item' => $component,
                    'quantity' => $quantity,
                    'gross' => $gross,
                    'stockLot' => $stockLot,
                    'unitPrice' => $unitPrice,
                    'costPrice' => $costPrice,
                ];
                $totalGross = $totalGross->plus($gross);
            }

            $discounts = $this->discountsFor($kit, $parts, $totalGross);

            $lines = [];
            foreach ($parts as $index => $part) {
                $lines[] = CartLine::create([
                    'cart_id' => $cart->id,
                    'line_number' => $cart->nextLineNumber(),
                    'item_id' => $part['item']->id,
                    'item_kit_id' => $kit->id,
                    'stock_lot_id' => $part['stockLot']?->id,
                    'stock_location_id' => $cart->stock_location_id,
                    'quantity' => $part['quantity'],
                    'unit_price' => $part['unitPrice'],
                    'cost_price' => $part['costPrice'],
                    'discount_value' => $discounts[$index]['value'],
                    'discount_type' => $discounts[$index]['type'],
                    'price_overridden' => false,
                ]);
            }

            return new Collection($lines);
        });
    }

    /**
     * @param  list<array{item: mixed, quantity: string, gross: Money}>  $parts
     * @return list<array{value: string, type: string}>
     */
    private function discountsFor(ItemKit $kit, array $parts, Money $totalGross): array
    {
        $count = count($parts);

        if ($kit->price_option === 'components' || bccomp((string) $kit->discount_value, '0', 4) === 0) {
            return array_fill(0, $count, ['value' => '0', 'type' => 'percent']);
        }

        if ($kit->discount_type === 'percent') {
            return array_fill(0, $count, ['value' => (string) $kit->discount_value, 'type' => 'percent']);
        }

        // Fixed kit discount: apportion the flat amount across components
        // proportional to each one's share of the kit's gross total,
        // remainder assigned to the last component -- same technique as
        // PromotionEngine::apportion(), reimplemented here rather than
        // shared (that method is private and tightly coupled to
        // PromotionEngine's line-number-keyed shape).
        $rawDiscount = MoneySupport::of((string) $kit->discount_value);
        $result = [];
        $allocated = MoneySupport::zero();

        foreach ($parts as $index => $part) {
            if ($index === $count - 1) {
                break;
            }

            $share = $totalGross->isZero()
                ? MoneySupport::zero()
                : $rawDiscount->multipliedBy(bcdiv((string) $part['gross']->getAmount(), (string) $totalGross->getAmount(), 10), RoundingMode::HalfUp);
            $share = $this->min($share, $part['gross']);

            $result[$index] = ['value' => (string) $share->getAmount(), 'type' => 'fixed'];
            $allocated = $allocated->plus($share);
        }

        $lastIndex = $count - 1;
        $remainder = $this->floorAtZero($rawDiscount->minus($allocated));
        $remainder = $this->min($remainder, $parts[$lastIndex]['gross']);
        $result[$lastIndex] = ['value' => (string) $remainder->getAmount(), 'type' => 'fixed'];

        return $result;
    }

    private function min(Money $a, Money $b): Money
    {
        return $a->isLessThan($b) ? $a : $b;
    }

    private function floorAtZero(Money $money): Money
    {
        return $money->isNegative() ? MoneySupport::zero() : $money;
    }
}
