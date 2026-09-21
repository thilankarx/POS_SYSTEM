<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockTransfers;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Actions\TransferStockAction;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\StockLocation;
use App\Support\Logging\DomainLog;
use App\Support\Money\Rules\ValidDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?int $item_id = null;

    #[Url]
    public ?int $from_location_id = null;

    public ?int $to_location_id = null;

    public string $quantity = '';

    public string $note = '';

    public function mount(): void
    {
        Gate::authorize('inventory.transfer');
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', Rule::exists('items', 'id')],
            // Rule::exists alone only proves each location exists, not that
            // this user may operate at it -- without this a clerk assigned
            // to one location could pull stock out of (or route it into)
            // any other, just by picking it in these ordinary wire:model
            // fields.
            'from_location_id' => ['required', Rule::in(auth()->user()->stockLocations()->pluck('stock_locations.id'))],
            'to_location_id' => ['required', 'different:from_location_id', Rule::in(auth()->user()->stockLocations()->pluck('stock_locations.id'))],
            'quantity' => ['required', new ValidDecimal, 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        try {
            app(TransferStockAction::class)->execute(
                item: Item::findOrFail($validated['item_id']),
                from: StockLocation::findOrFail($validated['from_location_id']),
                to: StockLocation::findOrFail($validated['to_location_id']),
                quantity: $validated['quantity'],
                user: auth()->user(),
                note: $validated['note'] ?: null,
            );
        } catch (InventoryException $e) {
            DomainLog::refused($e, ['from_location_id' => $validated['from_location_id'], 'to_location_id' => $validated['to_location_id'], 'item_id' => $validated['item_id']]);
            $this->addError('quantity', $e->getMessage());

            return;
        }

        session()->flash('status', 'Stock transferred.');
        $this->redirectRoute('stock-locations.stock', ['stockLocation' => $validated['to_location_id']]);
    }

    public function render()
    {
        $stockLocations = auth()->user()->stockLocations()->orderBy('name')->get();
        $item = $this->item_id ? Item::query()->whereKey($this->item_id)->first() : null;
        $sourceQuantity = $item && $this->from_location_id
            ? ($item->stockLevels()->where('stock_location_id', $this->from_location_id)->value('quantity') ?? '0.000')
            : null;
        $destinationQuantity = $item && $this->to_location_id
            ? ($item->stockLevels()->where('stock_location_id', $this->to_location_id)->value('quantity') ?? '0.000')
            : null;

        return view('livewire.inventory.stock-transfers.form', [
            'stockLocations' => $stockLocations,
            'items' => Item::active()->where('stock_type', Item::STOCK_TYPE_STOCKED)->orderBy('name')->get(),
            'selectedItem' => $item,
            'sourceQuantity' => $sourceQuantity,
            'destinationQuantity' => $destinationQuantity,
        ]);
    }
}
