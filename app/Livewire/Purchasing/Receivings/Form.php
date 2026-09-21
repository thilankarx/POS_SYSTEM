<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\Receivings;

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Data\PurchaseLinesTotal;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Purchasing\PurchaseLinePricer;
use App\Support\Logging\DomainLog;
use App\Support\Money\Rules\ValidDecimal;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    #[Url]
    public ?int $purchase_order = null;

    public ?PurchaseOrder $purchaseOrder = null;

    public ?int $supplier_id = null;

    public ?int $stock_location_id = null;

    public string $type = Receiving::TYPE_RECEIPT;

    public ?string $supplier_reference = null;

    public string $comment = '';

    /** @var array<int, array{item_id: ?int, quantity: string, unit_cost: string, purchase_order_line_id: ?int, lot_number: ?string, expires_on: ?string, selling_price: ?string, serials_text: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', Receiving::class);

        if ($this->purchase_order !== null) {
            $this->purchaseOrder = PurchaseOrder::with(['supplier', 'stockLocation', 'lines.item'])->findOrFail($this->purchase_order);
            $this->supplier_id = $this->purchaseOrder->supplier_id;
            $this->stock_location_id = $this->purchaseOrder->stock_location_id;
            $this->type = Receiving::TYPE_RECEIPT;

            foreach ($this->purchaseOrder->lines as $line) {
                $remaining = $this->purchaseOrder->remainingQuantityFor($line);
                if (bccomp($remaining, '0', 3) > 0) {
                    $this->lines[] = [
                        'item_id' => $line->item_id,
                        'quantity' => $remaining,
                        'unit_cost' => (string) $line->unit_cost->getAmount(),
                        'purchase_order_line_id' => $line->id,
                        'lot_number' => null,
                        'expires_on' => null,
                        'selling_price' => null,
                        'serials_text' => '',
                    ];
                }
            }
        }

        if ($this->lines === [] && $this->purchaseOrder === null) {
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id' => null,
            'quantity' => '1',
            'unit_cost' => '0.00',
            'purchase_order_line_id' => null,
            'lot_number' => null,
            'expires_on' => null,
            'selling_price' => null,
            'serials_text' => '',
        ];
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
            // Rule::exists alone only proves the location exists, not that
            // this user may operate at it -- an ordinary wire:model field,
            // no forging required to submit a receiving against a location
            // this user has no relationship to.
            'stock_location_id' => ['required', Rule::in(auth()->user()->stockLocations()->pluck('stock_locations.id'))],
            'type' => ['required', Rule::in($this->purchaseOrder !== null
                ? [Receiving::TYPE_RECEIPT]
                : [
                    Receiving::TYPE_RECEIPT, Receiving::TYPE_RETURN_TO_SUPPLIER,
                    Receiving::TYPE_TRANSFER_IN, Receiving::TYPE_TRANSFER_OUT,
                ])],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', new ValidDecimal],
            'lines.*.unit_cost' => ['required', new ValidMoneyAmount],
            'lines.*.lot_number' => ['nullable', 'string', 'max:255'],
            'lines.*.expires_on' => ['nullable', 'date'],
            'lines.*.selling_price' => ['nullable', new ValidMoneyAmount],
            'lines.*.serials_text' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseSerials(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($serial) => trim($serial))
            ->filter(fn ($serial) => $serial !== '')
            ->values()
            ->all();
    }

    public function save(): void
    {
        // Re-checked here, not just in mount(): every other mutating
        // component in this codebase re-authorizes in each mutator (mount()
        // only guards the initial page load), and this is the one place
        // that didn't -- ReceivingPolicy has no separate update ability
        // since a receiving has no edit step after creation, so this reuses
        // the same 'create' check mount() already made.
        Gate::authorize('create', Receiving::class);

        if ($this->purchaseOrder !== null) {
            $this->purchaseOrder->refresh()->load(['supplier', 'stockLocation', 'lines.item']);
            $this->supplier_id = $this->purchaseOrder->supplier_id;
            $this->stock_location_id = $this->purchaseOrder->stock_location_id;
            $this->type = Receiving::TYPE_RECEIPT;
        }

        $this->withValidator(function ($validator) {
            $validator->after(function ($validator) {
                $items = Item::whereIn('id', array_filter(array_column($this->lines, 'item_id')))->get()->keyBy('id');
                $purchaseOrderLines = $this->purchaseOrder?->lines->keyBy('id');
                $existingLots = StockLot::query()
                    ->whereIn('item_id', $items->keys())
                    ->get(['item_id', 'lot_number'])
                    ->keyBy(fn (StockLot $lot) => $lot->item_id.'|'.mb_strtolower(trim($lot->lot_number)));
                $enteredLots = [];
                $allSerials = [];

                foreach ($this->lines as $index => $line) {
                    $item = $line['item_id'] !== null ? $items->get((int) $line['item_id']) : null;

                    if ($item === null) {
                        continue;
                    }

                    $quantity = (string) $line['quantity'];
                    $isValidQuantity = preg_match('/^\d{1,15}(\.\d{1,4})?$/', $quantity) === 1;

                    if ($isValidQuantity && bccomp($quantity, '0', 3) <= 0) {
                        $validator->errors()->add("lines.{$index}.quantity", 'The received quantity must be greater than zero.');
                    }

                    if ($this->purchaseOrder !== null) {
                        $purchaseOrderLine = $purchaseOrderLines?->get((int) ($line['purchase_order_line_id'] ?? 0));

                        if ($purchaseOrderLine === null || $purchaseOrderLine->item_id !== $item->id) {
                            $validator->errors()->add("lines.{$index}.item_id", 'This item is not an outstanding line on the purchase order.');
                        } elseif ($isValidQuantity && bccomp($quantity, $this->purchaseOrder->remainingQuantityFor($purchaseOrderLine), 3) > 0) {
                            $validator->errors()->add("lines.{$index}.quantity", 'The received quantity cannot exceed the quantity remaining on the purchase order.');
                        }
                    }

                    if ($item->movesStock() && empty($line['lot_number'])) {
                        $validator->errors()->add("lines.{$index}.lot_number", 'A lot number is required — every stocked item is lot-tracked.');
                    }

                    if ($item->movesStock() && ! empty($line['lot_number'])) {
                        $lotNumber = trim((string) $line['lot_number']);
                        $lotKey = $item->id.'|'.mb_strtolower($lotNumber);

                        if (isset($enteredLots[$lotKey])) {
                            $validator->errors()->add("lines.{$index}.lot_number", "Lot number {$lotNumber} is entered more than once for this item.");
                        } elseif (in_array($this->type, [Receiving::TYPE_RECEIPT, Receiving::TYPE_TRANSFER_IN], true)
                            && $existingLots->has($lotKey)) {
                            $validator->errors()->add("lines.{$index}.lot_number", "Lot number {$lotNumber} already exists for this item.");
                        }

                        $enteredLots[$lotKey] = true;
                    }

                    if ($item->movesStock() && empty($line['selling_price'])) {
                        $validator->errors()->add("lines.{$index}.selling_price", 'A selling price is required — it prices every sale made from this lot.');
                    }

                    if ($item->has_expiry && empty($line['expires_on'])) {
                        $validator->errors()->add("lines.{$index}.expires_on", 'This item has an expiry date — an expiry date is required.');
                    }

                    if ($item->is_serialized && $this->type === Receiving::TYPE_RECEIPT) {
                        $serials = $this->parseSerials((string) $line['serials_text']);
                        $isWholeNumber = $isValidQuantity && bccomp($quantity, (string) intval($quantity), 3) === 0;

                        if (! $isWholeNumber) {
                            $validator->errors()->add("lines.{$index}.quantity", 'This item is serialized — quantity must be a whole number.');
                        } elseif (count($serials) !== (int) $quantity) {
                            $validator->errors()->add("lines.{$index}.serials_text", 'Enter exactly one serial number per unit received ('.(int) $quantity.' expected, '.count($serials).' given).');
                        }

                        foreach ($serials as $serial) {
                            if (in_array($serial, $allSerials, true)) {
                                $validator->errors()->add("lines.{$index}.serials_text", "Serial \"{$serial}\" is entered more than once.");
                            }
                            $allSerials[] = $serial;
                        }
                    }
                }

                if ($allSerials !== [] && SerialNumber::whereIn('serial', $allSerials)->exists()) {
                    $validator->errors()->add('lines', 'One or more serial numbers already exist in inventory.');
                }
            });
        });

        $validated = $this->validate();

        foreach ($validated['lines'] as $index => $line) {
            $validated['lines'][$index]['serials'] = $this->parseSerials((string) ($line['serials_text'] ?? ''));
            unset($validated['lines'][$index]['serials_text']);
        }

        try {
            $receiving = app(ReceiveGoodsAction::class)->execute(
                purchaseOrder: $this->purchaseOrder,
                supplier: Supplier::findOrFail($validated['supplier_id']),
                location: StockLocation::findOrFail($validated['stock_location_id']),
                user: auth()->user(),
                type: $validated['type'],
                lines: $validated['lines'],
                supplierReference: $validated['supplier_reference'] ?: null,
                comment: $validated['comment'] ?: null,
            );
        } catch (PurchasingException $e) {
            DomainLog::refused($e, ['purchase_order_id' => $this->purchaseOrder?->id, 'stock_location_id' => $this->stock_location_id]);
            $this->addError('lines', $e->getMessage());

            return;
        }

        session()->flash('status', "Receiving {$receiving->number} recorded.");
        $this->redirectRoute('receivings.show', $receiving);
    }

    public function render()
    {
        $this->purchaseOrder?->loadMissing(['supplier', 'stockLocation', 'lines.item']);
        $purchaseOrderItemIds = $this->purchaseOrder?->lines->pluck('item_id')->all() ?? [];

        return view('livewire.purchasing.receivings.form', [
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'stockLocations' => auth()->user()->stockLocations()->orderBy('name')->get(),
            'items' => Item::query()
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->when($purchaseOrderItemIds !== [], fn ($query) => $query->orWhereIn('id', $purchaseOrderItemIds)))
                ->orderBy('name')
                ->get(),
            'preview' => $this->preview(),
        ]);
    }

    private function preview(): PurchaseLinesTotal
    {
        return app(PurchaseLinePricer::class)->price($this->lines);
    }
}
