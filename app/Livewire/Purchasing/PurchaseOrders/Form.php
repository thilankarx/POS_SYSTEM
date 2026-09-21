<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\PurchaseOrders;

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\ApprovePurchaseOrderAction;
use App\Domain\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\SubmitPurchaseOrderAction;
use App\Domain\Purchasing\Actions\UpdatePurchaseOrderLinesAction;
use App\Domain\Purchasing\Data\PurchaseLinesTotal;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\PurchaseLinePricer;
use App\Support\Logging\DomainLog;
use App\Support\Money\Money as MoneySupport;
use App\Support\Money\Rules\ValidDecimal;
use App\Support\Money\Rules\ValidMoneyAmount;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?PurchaseOrder $purchaseOrder = null;

    public ?int $supplier_id = null;

    public ?int $stock_location_id = null;

    public ?string $expected_on = null;

    public string $note = '';

    /** @var array<int, array{item_id: ?int, quantity_ordered: string, unit_cost: string}> */
    public array $lines = [];

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        $this->purchaseOrder = $purchaseOrder;

        if ($purchaseOrder !== null) {
            Gate::authorize('view', $purchaseOrder);

            $this->loadPurchaseOrderDetails();

            $this->supplier_id = $purchaseOrder->supplier_id;
            $this->stock_location_id = $purchaseOrder->stock_location_id;
            $this->expected_on = $purchaseOrder->expected_on?->toDateString();
            $this->note = (string) $purchaseOrder->note;
            $this->lines = $purchaseOrder->lines->map(fn ($line) => [
                'item_id' => $line->item_id,
                'quantity_ordered' => (string) $line->quantity_ordered,
                'unit_cost' => (string) $line->unit_cost->getAmount(),
            ])->all();
        } else {
            Gate::authorize('create', PurchaseOrder::class);
            $this->addLine();
        }
    }

    public function isEditable(): bool
    {
        return $this->purchaseOrder === null || $this->purchaseOrder->status === PurchaseOrder::STATUS_DRAFT;
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => null, 'quantity_ordered' => '1', 'unit_cost' => '0.00'];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'stock_location_id' => ['required', Rule::exists('stock_locations', 'id')],
            'expected_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')],
            'lines.*.quantity_ordered' => ['required', new ValidDecimal, 'gt:0'],
            'lines.*.unit_cost' => ['required', new ValidMoneyAmount],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->purchaseOrder !== null) {
            Gate::authorize('update', $this->purchaseOrder);

            try {
                $this->purchaseOrder->update([
                    'supplier_id' => $validated['supplier_id'],
                    'stock_location_id' => $validated['stock_location_id'],
                    'expected_on' => $validated['expected_on'],
                    'note' => $validated['note'],
                ]);
                app(UpdatePurchaseOrderLinesAction::class)->execute($this->purchaseOrder, $validated['lines']);
            } catch (PurchasingException $e) {
                DomainLog::refused($e, ['purchase_order_id' => $this->purchaseOrder?->id]);
                $this->addError('lines', $e->getMessage());

                return;
            }
        } else {
            Gate::authorize('create', PurchaseOrder::class);

            $this->purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
                supplier: Supplier::findOrFail($validated['supplier_id']),
                location: StockLocation::findOrFail($validated['stock_location_id']),
                creator: auth()->user(),
                lines: $validated['lines'],
                expectedOn: $validated['expected_on'],
                note: $validated['note'] ?: null,
            );
        }

        session()->flash('status', 'Purchase order saved.');
        $this->redirectRoute('purchase-orders.edit', $this->purchaseOrder);
    }

    public function submit(): void
    {
        Gate::authorize('update', $this->purchaseOrder);

        try {
            app(SubmitPurchaseOrderAction::class)->execute($this->purchaseOrder);
        } catch (PurchasingException $e) {
            DomainLog::refused($e, ['purchase_order_id' => $this->purchaseOrder?->id]);
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->loadPurchaseOrderDetails(refresh: true);
        session()->flash('status', 'Purchase order submitted for approval.');
    }

    public function approve(): void
    {
        Gate::authorize('approve', $this->purchaseOrder);

        try {
            app(ApprovePurchaseOrderAction::class)->execute($this->purchaseOrder, auth()->user());
        } catch (PurchasingException $e) {
            DomainLog::refused($e, ['purchase_order_id' => $this->purchaseOrder?->id]);
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->loadPurchaseOrderDetails(refresh: true);
        session()->flash('status', 'Purchase order approved.');
    }

    public function cancel(): void
    {
        Gate::authorize('update', $this->purchaseOrder);

        try {
            app(CancelPurchaseOrderAction::class)->execute($this->purchaseOrder);
        } catch (PurchasingException $e) {
            DomainLog::refused($e, ['purchase_order_id' => $this->purchaseOrder?->id]);
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->loadPurchaseOrderDetails(refresh: true);
        session()->flash('status', 'Purchase order cancelled.');
    }

    public function render()
    {
        $this->loadPurchaseOrderDetails();
        $purchaseOrderItemIds = $this->purchaseOrder?->lines->pluck('item_id')->all() ?? [];

        return view('livewire.purchasing.purchase-orders.form', [
            'suppliers' => $this->isEditable() ? Supplier::orderBy('company_name')->get() : collect(),
            'stockLocations' => $this->isEditable() ? StockLocation::orderBy('name')->get() : collect(),
            'items' => $this->isEditable()
                ? Item::query()
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->when($purchaseOrderItemIds !== [], fn ($query) => $query->orWhereIn('id', $purchaseOrderItemIds)))
                    ->orderBy('name')
                    ->get()
                : collect(),
            'preview' => $this->isEditable() ? $this->preview() : null,
            'lineTotals' => $this->isEditable() ? $this->lineTotals() : [],
            'fulfilment' => $this->fulfilmentSummary(),
        ]);
    }

    private function loadPurchaseOrderDetails(bool $refresh = false): void
    {
        if ($this->purchaseOrder === null) {
            return;
        }

        if ($refresh) {
            $this->purchaseOrder->refresh();
        }

        $this->purchaseOrder->loadMissing([
            'supplier',
            'stockLocation',
            'createdBy',
            'approvedBy',
            'lines.item',
            'receivings' => fn ($query) => $query->orderByDesc('received_at'),
            'receivings.user',
            'receivings.lines.item',
            'receivings.lines.stockLot',
        ]);
    }

    /** @return array{ordered: string, received: string, remaining: string, percentage: int} */
    private function fulfilmentSummary(): array
    {
        $ordered = '0.000';
        $received = '0.000';

        foreach ($this->purchaseOrder?->lines ?? [] as $line) {
            $ordered = bcadd($ordered, (string) $line->quantity_ordered, 3);
            $received = bcadd($received, (string) $line->quantity_received, 3);
        }

        $remaining = bcsub($ordered, $received, 3);
        if (bccomp($remaining, '0', 3) < 0) {
            $remaining = '0.000';
        }

        $percentage = bccomp($ordered, '0', 3) > 0
            ? min(100, (int) round(((float) $received / (float) $ordered) * 100))
            : 0;

        return compact('ordered', 'received', 'remaining', 'percentage');
    }

    private function preview(): PurchaseLinesTotal
    {
        return app(PurchaseLinePricer::class)->price(array_map(
            fn (array $line) => ['item_id' => $line['item_id'], 'quantity' => $line['quantity_ordered'], 'unit_cost' => $line['unit_cost']],
            $this->lines,
        ));
    }

    /** @return array<int, Money> */
    private function lineTotals(): array
    {
        return array_map(function (array $line): Money {
            $quantity = trim((string) $line['quantity_ordered']);
            $unitCost = trim((string) $line['unit_cost']);

            if (preg_match('/^\d{1,15}(\.\d{1,4})?$/', $quantity) !== 1
                || preg_match('/^\d{1,15}(\.\d{1,2})?$/', $unitCost) !== 1) {
                return MoneySupport::zero();
            }

            return MoneySupport::of($unitCost)->multipliedBy($quantity, RoundingMode::HalfUp);
        }, $this->lines);
    }
}
