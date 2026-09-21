<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\PurchaseLinePricer;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseOrderAction
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly PurchaseLinePricer $pricer,
    ) {}

    /**
     * @param  array<int, array{item_id: int, quantity_ordered: string, unit_cost: string}>  $lines
     */
    public function execute(
        Supplier $supplier,
        StockLocation $location,
        User $creator,
        array $lines,
        ?string $expectedOn = null,
        ?string $note = null,
    ): PurchaseOrder {
        if ($lines === []) {
            throw PurchasingException::emptyLines();
        }

        return DB::transaction(function () use ($supplier, $location, $creator, $lines, $expectedOn, $note) {
            $purchaseOrder = PurchaseOrder::create([
                'number' => $this->numbers->next('purchase_order'),
                'supplier_id' => $supplier->id,
                'stock_location_id' => $location->id,
                'created_by_user_id' => $creator->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'expected_on' => $expectedOn,
                'note' => $note,
            ]);

            $this->writeLines($purchaseOrder, $lines);

            return $purchaseOrder->fresh('lines');
        });
    }

    /**
     * @param  array<int, array{item_id: int, quantity_ordered: string, unit_cost: string}>  $lines
     */
    public function writeLines(PurchaseOrder $purchaseOrder, array $lines): void
    {
        $totals = $this->pricer->price(array_map(
            fn (array $line) => ['item_id' => $line['item_id'], 'quantity' => $line['quantity_ordered'], 'unit_cost' => $line['unit_cost']],
            $lines,
        ));

        foreach ($lines as $index => $line) {
            $purchaseOrder->lines()->create([
                'item_id' => $line['item_id'],
                'line_number' => $index + 1,
                'quantity_ordered' => $line['quantity_ordered'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => MoneySupport::of($line['unit_cost'])->multipliedBy($line['quantity_ordered'], RoundingMode::HalfUp),
            ]);
        }

        $purchaseOrder->update([
            'subtotal' => $totals->subtotal,
            'tax_total' => $totals->taxTotal,
            'total' => $totals->total,
        ]);
    }
}
