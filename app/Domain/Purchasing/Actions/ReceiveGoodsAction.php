<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Purchasing\PurchaseLinePricer;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Records goods physically arriving: writes the receiving + its lines,
 * moves stock through `InventoryService` (the only path stock is allowed to
 * change), and — when this receiving is against a purchase order — advances
 * that order's received quantities and status.
 */
final class ReceiveGoodsAction
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly InventoryService $inventory,
        private readonly PurchaseLinePricer $pricer,
    ) {}

    /**
     * @param  array<int, array{item_id: int, quantity: string, unit_cost: string, purchase_order_line_id?: int|null, lot_number?: string|null, expires_on?: string|null, selling_price?: string|null, description?: string|null, serials?: array<int, string>|null}>  $lines
     */
    public function execute(
        ?PurchaseOrder $purchaseOrder,
        Supplier $supplier,
        StockLocation $location,
        User $user,
        string $type,
        array $lines,
        ?string $supplierReference = null,
        ?string $comment = null,
    ): Receiving {
        if ($lines === []) {
            throw PurchasingException::emptyLines();
        }

        if ($purchaseOrder !== null && $purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
            throw PurchasingException::alreadyClosed();
        }

        return DB::transaction(function () use ($purchaseOrder, $supplier, $location, $user, $type, $lines, $supplierReference, $comment) {
            $receiving = Receiving::create([
                'number' => $this->numbers->next('receiving'),
                'purchase_order_id' => $purchaseOrder?->id,
                'supplier_id' => $supplier->id,
                'stock_location_id' => $location->id,
                'user_id' => $user->id,
                'type' => $type,
                'supplier_reference' => $supplierReference,
                'comment' => $comment,
                'received_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                $item = Item::findOrFail($line['item_id']);
                $lineTotal = MoneySupport::of($line['unit_cost'])->multipliedBy($line['quantity'], RoundingMode::HalfUp);

                // Defense in depth beyond the receiving form's own
                // validation -- every stocked item is lot-tracked now, so
                // no caller (form, API, test) can create a receiving line
                // for one without a lot number.
                if ($item->movesStock() && empty($line['lot_number'])) {
                    throw PurchasingException::lotNumberRequired($item->name);
                }

                if ($item->has_expiry && empty($line['expires_on'])) {
                    throw PurchasingException::expiryDateRequired($item->name);
                }

                $stockLot = null;
                if (! empty($line['lot_number'])) {
                    $lotNumber = trim((string) $line['lot_number']);
                    $existingLot = StockLot::query()
                        ->where('item_id', $item->id)
                        ->where('lot_number', $lotNumber)
                        ->first();

                    if ($this->addsStock($type) && $existingLot !== null) {
                        throw PurchasingException::duplicateLotNumber($item->name, $lotNumber);
                    }

                    try {
                        $stockLot = $existingLot ?? StockLot::create([
                            'item_id' => $item->id,
                            'lot_number' => $lotNumber,
                            'expires_on' => $line['expires_on'] ?? null,
                            'cost_price' => $line['unit_cost'],
                            'selling_price' => $line['selling_price'] ?? null,
                        ]);
                    } catch (QueryException $exception) {
                        if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                            throw PurchasingException::duplicateLotNumber($item->name, $lotNumber);
                        }

                        throw $exception;
                    }
                }

                $receiving->lines()->create([
                    'item_id' => $item->id,
                    'stock_lot_id' => $stockLot?->id,
                    'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                    'line_number' => $index + 1,
                    'description' => $line['description'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $lineTotal,
                ]);

                if ($item->movesStock()) {
                    $delta = $this->addsStock($type) ? (string) $line['quantity'] : bcmul((string) $line['quantity'], '-1', 3);

                    $this->inventory->record(
                        item: $item,
                        stockLocationId: $location->id,
                        quantityDelta: $delta,
                        reason: match ($type) {
                            Receiving::TYPE_RECEIPT => StockMovement::REASON_RECEIVING,
                            Receiving::TYPE_RETURN_TO_SUPPLIER => StockMovement::REASON_RETURN_TO_SUPPLIER,
                            default => StockMovement::REASON_TRANSFER,
                        },
                        source: $receiving,
                        userId: $user->id,
                        stockLotId: $stockLot?->id,
                        unitCost: (string) MoneySupport::of($line['unit_cost'])->getAmount(),
                    );
                }

                if ($item->is_serialized && $type === Receiving::TYPE_RECEIPT && ! empty($line['serials'])) {
                    foreach ($line['serials'] as $serial) {
                        SerialNumber::create([
                            'item_id' => $item->id,
                            'serial' => $serial,
                            'stock_location_id' => $location->id,
                            'stock_lot_id' => $stockLot?->id,
                            'status' => SerialNumber::STATUS_IN_STOCK,
                        ]);
                    }
                }

                if ($purchaseOrder !== null && ! empty($line['purchase_order_line_id'])) {
                    $purchaseOrderLine = $purchaseOrder->lines->firstWhere('id', $line['purchase_order_line_id']);
                    $purchaseOrderLine?->increment('quantity_received', $line['quantity']);
                }
            }

            $totals = $this->pricer->price(array_map(
                fn (array $line) => ['item_id' => $line['item_id'], 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost']],
                $lines,
            ));

            $receiving->update([
                'subtotal' => $totals->subtotal,
                'tax_total' => $totals->taxTotal,
                'total' => $totals->total,
            ]);

            if ($purchaseOrder !== null) {
                $purchaseOrder->refresh();
                $purchaseOrder->update([
                    'status' => $purchaseOrder->fresh('lines')->isFullyReceived()
                        ? PurchaseOrder::STATUS_RECEIVED
                        : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ]);
            }

            return $receiving->fresh('lines');
        });
    }

    private function addsStock(string $type): bool
    {
        return in_array($type, [Receiving::TYPE_RECEIPT, Receiving::TYPE_TRANSFER_IN], true);
    }
}
