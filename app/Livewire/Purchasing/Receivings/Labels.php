<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\Receivings;

use App\Domain\Inventory\Actions\PrintItemLabelsAction;
use App\Domain\Inventory\Exceptions\LabelPrintingException;
use App\Domain\Purchasing\Models\Receiving;
use App\Support\Logging\DomainLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Labels extends Component
{
    public Receiving $receiving;

    /** @var array<int, string> */
    public array $quantity = [];

    public function mount(Receiving $receiving): void
    {
        Gate::authorize('printLabels', $receiving);

        $this->receiving = $receiving->load('lines.item.barcodes', 'lines.stockLot', 'stockLocation');

        foreach ($this->receiving->lines as $line) {
            $canPrint = $line->item !== null
                && $line->stock_lot_id !== null
                && $line->stockLot?->selling_price !== null;

            $this->quantity[$line->id] = $canPrint ? (string) (int) $line->quantity : '0';
        }
    }

    public function printLabels(): void
    {
        Gate::authorize('printLabels', $this->receiving);

        $this->validate([
            'quantity.*' => ['required', 'integer', 'min:0'],
        ]);

        $lines = [];
        foreach ($this->receiving->lines as $line) {
            $qty = (int) ($this->quantity[$line->id] ?? '0');
            if ($qty === 0) {
                continue;
            }

            if ($line->item === null || $line->stock_lot_id === null || $line->stockLot === null) {
                $this->addError("quantity.{$line->id}", 'This line is not linked to a stock lot and cannot be printed.');

                return;
            }

            if ($line->stockLot->selling_price === null) {
                $this->addError("quantity.{$line->id}", 'Add a selling price to this stock lot before printing its labels.');

                return;
            }

            $lines[] = ['item' => $line->item, 'stock_lot_id' => $line->stock_lot_id, 'quantity' => $qty];
        }

        if ($lines === []) {
            $this->addError('quantity', 'Enter a quantity greater than zero for at least one line.');

            return;
        }

        try {
            app(PrintItemLabelsAction::class)->execute($this->receiving->stockLocation, $lines);
        } catch (LabelPrintingException $e) {
            DomainLog::refused($e, ['receiving_id' => $this->receiving->id, 'stock_location_id' => $this->receiving->stock_location_id]);
            $this->addError('printer', $e->getMessage());

            return;
        }

        session()->flash('status', 'Labels sent to the printer.');
    }

    public function render()
    {
        return view('livewire.purchasing.receivings.labels');
    }
}
