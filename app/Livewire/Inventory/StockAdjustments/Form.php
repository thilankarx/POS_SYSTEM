<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockAdjustments;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Actions\AdjustStockAction;
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
    #[Url]
    public ?int $stock_location_id = null;

    public ?int $item_id = null;

    public string $direction = 'increase';

    public string $quantity = '';

    public string $note = '';

    public function mount(): void
    {
        Gate::authorize('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            // Rule::exists alone only proves the location exists, not that
            // this user may operate at it -- inventory.adjust in mount() is
            // a bare permission check with no location scoping, so without
            // this a stock clerk assigned to Warehouse A could simply set
            // this field to Warehouse B (an ordinary wire:model property,
            // no forging required) and adjust stock they have no
            // relationship to.
            'stock_location_id' => ['required', Rule::in(auth()->user()->stockLocations()->pluck('stock_locations.id'))],
            'item_id' => ['required', Rule::exists('items', 'id')],
            'direction' => ['required', 'in:increase,decrease'],
            'quantity' => ['required', new ValidDecimal, 'gt:0'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $delta = $validated['direction'] === 'decrease'
            ? bcmul($validated['quantity'], '-1', 3)
            : $validated['quantity'];

        try {
            app(AdjustStockAction::class)->execute(
                item: Item::findOrFail($validated['item_id']),
                location: StockLocation::findOrFail($validated['stock_location_id']),
                delta: $delta,
                note: $validated['note'],
                user: auth()->user(),
            );
        } catch (InventoryException $e) {
            DomainLog::refused($e, ['stock_location_id' => $validated['stock_location_id'], 'item_id' => $validated['item_id']]);
            $this->addError('note', $e->getMessage());

            return;
        }

        session()->flash('status', 'Stock adjusted.');
        $this->redirectRoute('stock-locations.stock', ['stockLocation' => $validated['stock_location_id']]);
    }

    public function render()
    {
        return view('livewire.inventory.stock-adjustments.form', [
            // Scoped to match the validation in rules() -- otherwise the
            // dropdown would offer locations the user isn't actually
            // allowed to submit.
            'stockLocations' => auth()->user()->stockLocations()->orderBy('name')->get(),
            'items' => Item::active()->orderBy('name')->get(),
        ]);
    }
}
