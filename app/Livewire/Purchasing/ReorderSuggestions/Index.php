<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\ReorderSuggestions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Queries\ReorderSuggestionsQuery;
use App\Support\Money\Money as MoneySupport;
use App\Support\Money\Rules\ValidDecimal;
use App\Support\Money\Rules\ValidMoneyAmount;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url(except: '')]
    public ?int $stock_location_id = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $urgency = '';

    /** @var array<int, bool> item_id => selected */
    public array $selected = [];

    /** @var array<int, string> item_id => quantity to order */
    public array $quantities = [];

    /** @var array<int, string> item_id => unit cost */
    public array $unitCosts = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $this->stock_location_id ??= StockLocation::where('is_default', true)->value('id')
            ?? StockLocation::query()->value('id');
    }

    public function updatedStockLocationId(): void
    {
        $this->selected = [];
        $this->quantities = [];
        $this->unitCosts = [];
    }

    public function selectSupplier(int $supplierId, bool $selected): void
    {
        foreach ($this->suggestionsForSupplier($supplierId) as $item) {
            $this->selected[$item->id] = $selected;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'urgency']);
    }

    public function createPurchaseOrder(int $supplierId): void
    {
        Gate::authorize('create', PurchaseOrder::class);

        $lines = [];
        $rules = [];
        foreach ($this->suggestionsForSupplier($supplierId) as $item) {
            if (empty($this->selected[$item->id])) {
                continue;
            }

            $rules["quantities.{$item->id}"] = ['required', new ValidDecimal, 'gt:0'];
            $rules["unitCosts.{$item->id}"] = ['required', new ValidMoneyAmount];

            $quantity = $this->quantities[$item->id] ?? '0';

            $lines[] = [
                'item_id' => $item->id,
                'quantity_ordered' => $quantity,
                'unit_cost' => $this->unitCosts[$item->id] ?? '0',
            ];
        }

        if ($lines === []) {
            $this->addError('lines', 'Select at least one item with a positive quantity.');

            return;
        }

        $this->validate($rules);

        $purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
            supplier: Supplier::findOrFail($supplierId),
            location: StockLocation::findOrFail($this->stock_location_id),
            creator: auth()->user(),
            lines: $lines,
        );

        session()->flash('status', 'Purchase order created from reorder suggestions.');
        $this->redirectRoute('purchase-orders.edit', $purchaseOrder);
    }

    /**
     * @return Collection<int, Item>
     */
    private function suggestionsForSupplier(int $supplierId)
    {
        return $this->suggestions()->where('supplier_id', $supplierId);
    }

    private function suggestions()
    {
        $location = StockLocation::findOrFail($this->stock_location_id);

        return app(ReorderSuggestionsQuery::class)->forLocation($location);
    }

    private function visibleSuggestions(Collection $suggestions): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $suggestions
            ->when($search !== '', fn (Collection $items) => $items->filter(fn (Item $item) => str_contains(mb_strtolower($item->name), $search)
                || str_contains(mb_strtolower($item->sku), $search)
                || str_contains(mb_strtolower((string) $item->supplier?->company_name), $search)))
            ->when($this->urgency === 'out_of_stock', fn (Collection $items) => $items->filter(fn (Item $item) => bccomp((string) $item->on_hand, '0', 3) <= 0))
            ->when($this->urgency === 'low_stock', fn (Collection $items) => $items->filter(fn (Item $item) => bccomp((string) $item->on_hand, '0', 3) > 0))
            ->values();
    }

    /** @return array<int, Money> */
    private function lineTotals(Collection $suggestions): array
    {
        $totals = [];

        foreach ($suggestions as $item) {
            $quantity = trim((string) ($this->quantities[$item->id] ?? ''));
            $unitCost = trim((string) ($this->unitCosts[$item->id] ?? ''));

            $totals[$item->id] = preg_match('/^\d{1,15}(\.\d{1,4})?$/', $quantity) === 1
                && preg_match('/^\d{1,15}(\.\d{1,2})?$/', $unitCost) === 1
                    ? MoneySupport::of($unitCost)->multipliedBy($quantity, RoundingMode::HalfUp)
                    : MoneySupport::zero();
        }

        return $totals;
    }

    public function render()
    {
        $query = app(ReorderSuggestionsQuery::class);
        $suggestions = $this->suggestions();

        $lotsByItem = StockLot::query()
            ->whereIn('item_id', $suggestions->pluck('id'))
            ->fefo()
            ->get()
            ->groupBy('item_id');

        foreach ($suggestions as $item) {
            if (! array_key_exists($item->id, $this->quantities)) {
                $this->selected[$item->id] = true;
                $this->quantities[$item->id] = $query->suggestedQuantity($item);
                $lastLot = $lotsByItem->get($item->id)?->first();
                $this->unitCosts[$item->id] = $lastLot?->cost_price !== null ? (string) $lastLot->cost_price->getAmount() : '0.00';
            }
        }

        $visibleSuggestions = $this->visibleSuggestions($suggestions);
        $lineTotals = $this->lineTotals($suggestions);
        $supplierTotals = [];
        $supplierSelectedCounts = [];

        foreach ($suggestions as $item) {
            $supplierTotals[$item->supplier_id] ??= MoneySupport::zero();
            $supplierSelectedCounts[$item->supplier_id] ??= 0;

            if (! empty($this->selected[$item->id])) {
                $supplierTotals[$item->supplier_id] = $supplierTotals[$item->supplier_id]->plus($lineTotals[$item->id]);
                $supplierSelectedCounts[$item->supplier_id]++;
            }
        }

        return view('livewire.purchasing.reorder-suggestions.index', [
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'bySupplier' => $visibleSuggestions->groupBy(fn ($item) => $item->supplier_id),
            'summary' => [
                'items' => $suggestions->count(),
                'suppliers' => $suggestions->pluck('supplier_id')->unique()->count(),
                'outOfStock' => $suggestions->filter(fn (Item $item) => bccomp((string) $item->on_hand, '0', 3) <= 0)->count(),
                'selected' => $suggestions->filter(fn (Item $item) => ! empty($this->selected[$item->id]))->count(),
            ],
            'lineTotals' => $lineTotals,
            'supplierTotals' => $supplierTotals,
            'supplierSelectedCounts' => $supplierSelectedCounts,
        ]);
    }
}
