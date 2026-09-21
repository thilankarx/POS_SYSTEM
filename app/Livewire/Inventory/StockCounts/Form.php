<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockCounts;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Actions\ApproveStockCountAction;
use App\Domain\Inventory\Actions\CancelStockCountAction;
use App\Domain\Inventory\Actions\CreateStockCountAction;
use App\Domain\Inventory\Actions\GenerateStockCountLinesAction;
use App\Domain\Inventory\Actions\RecordCountedQuantityAction;
use App\Domain\Inventory\Actions\SubmitStockCountForReviewAction;
use App\Domain\Inventory\Actions\UnapproveStockCountAction;
use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockLocation;
use App\Support\Logging\DomainLog;
use App\Support\Money\Rules\ValidDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?StockCount $stockCount = null;

    public ?int $stock_location_id = null;

    public bool $is_blind = true;

    public string $note = '';

    public bool $countAllItems = true;

    /** @var array<int, int> */
    public array $selectedItemIds = [];

    /** @var array<int, string> keyed by stock_count_line id */
    public array $counts = [];

    public function mount(?StockCount $stockCount = null): void
    {
        $this->stockCount = $stockCount;

        if ($stockCount !== null) {
            Gate::authorize('view', $stockCount);

            $this->stock_location_id = $stockCount->stock_location_id;
            $this->is_blind = $stockCount->is_blind;
            $this->note = (string) $stockCount->note;

            foreach ($stockCount->lines as $line) {
                $this->counts[$line->id] = $line->counted_quantity !== null ? (string) $line->counted_quantity : '';
            }
        } else {
            Gate::authorize('create', StockCount::class);
        }
    }

    public function createDraft(): void
    {
        Gate::authorize('create', StockCount::class);

        $validated = $this->validate([
            'stock_location_id' => ['required', Rule::exists('stock_locations', 'id')],
            'is_blind' => ['boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $this->stockCount = app(CreateStockCountAction::class)->execute(
            location: StockLocation::findOrFail($validated['stock_location_id']),
            user: auth()->user(),
            isBlind: $validated['is_blind'],
            note: $validated['note'] ?: null,
        );

        session()->flash('status', 'Stock count created.');
        $this->redirectRoute('stock-counts.edit', $this->stockCount);
    }

    public function generateLines(): void
    {
        Gate::authorize('update', $this->stockCount);

        try {
            $this->stockCount = app(GenerateStockCountLinesAction::class)->execute(
                $this->stockCount,
                $this->countAllItems ? [] : $this->selectedItemIds,
            );
        } catch (StockCountException $e) {
            DomainLog::refused($e, ['stock_count_id' => $this->stockCount?->id]);
            $this->addError('lines', $e->getMessage());

            return;
        }

        foreach ($this->stockCount->lines as $line) {
            $this->counts[$line->id] = '';
        }

        session()->flash('status', 'Count lines generated.');
    }

    public function saveCounts(): void
    {
        Gate::authorize('update', $this->stockCount);

        $this->validate($this->countLineRules());

        try {
            foreach ($this->stockCount->lines as $line) {
                $value = $this->counts[$line->id] ?? '';

                if ($value === '') {
                    continue;
                }

                app(RecordCountedQuantityAction::class)->execute($line, $value, auth()->user());
            }
        } catch (StockCountException $e) {
            DomainLog::refused($e, ['stock_count_id' => $this->stockCount?->id]);
            $this->addError('counts', $e->getMessage());

            return;
        }

        $this->stockCount = $this->stockCount->fresh('lines');
        session()->flash('status', 'Counts saved.');
    }

    public function submit(): void
    {
        Gate::authorize('update', $this->stockCount);

        try {
            $this->stockCount = app(SubmitStockCountForReviewAction::class)->execute($this->stockCount);
        } catch (StockCountException $e) {
            DomainLog::refused($e, ['stock_count_id' => $this->stockCount?->id]);
            $this->addError('counts', $e->getMessage());

            return;
        }

        session()->flash('status', 'Stock count submitted for review.');
    }

    public function approve(): void
    {
        Gate::authorize('approve', $this->stockCount);

        try {
            $this->stockCount = app(ApproveStockCountAction::class)->execute($this->stockCount, auth()->user());
        } catch (StockCountException $e) {
            DomainLog::refused($e, ['stock_count_id' => $this->stockCount?->id]);
            $this->addError('counts', $e->getMessage());

            return;
        }

        session()->flash('status', 'Stock count approved and posted to the ledger.');
    }

    public function unapprove(): void
    {
        Gate::authorize('unapprove', $this->stockCount);

        try {
            $this->stockCount = app(UnapproveStockCountAction::class)->execute($this->stockCount, auth()->user());
        } catch (StockCountException $e) {
            DomainLog::refused($e, ['stock_count_id' => $this->stockCount?->id]);
            $this->addError('counts', $e->getMessage());

            return;
        }

        session()->flash('status', 'Stock count unapproved; ledger movements reversed.');
    }

    public function cancel(): void
    {
        Gate::authorize('update', $this->stockCount);

        try {
            $this->stockCount = app(CancelStockCountAction::class)->execute($this->stockCount);
        } catch (StockCountException $e) {
            DomainLog::refused($e, ['stock_count_id' => $this->stockCount?->id]);
            $this->addError('counts', $e->getMessage());

            return;
        }

        session()->flash('status', 'Stock count cancelled.');
    }

    public function countLineRules(): array
    {
        return [
            'counts.*' => ['nullable', new ValidDecimal],
        ];
    }

    public function render()
    {
        return view('livewire.inventory.stock-counts.form', [
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'items' => Item::active()->orderBy('name')->get(),
        ]);
    }
}
