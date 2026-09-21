<?php

declare(strict_types=1);

namespace App\Livewire\Sales\Terminals;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Terminal;
use App\Support\Printing\Rules\ValidNetworkPrinterTarget;
use App\Support\Printing\WindowsPrinterDiscovery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Terminal $terminal = null;

    public string $name = '';

    public string $code = '';

    public ?int $stock_location_id = null;

    public string $printer_connector = '';

    public string $receipt_printer = '';

    public string $printer_paper_width = '80';

    public bool $is_active = true;

    public function mount(?Terminal $terminal = null): void
    {
        $this->terminal = $terminal;

        if ($terminal !== null) {
            Gate::authorize('update', $terminal);

            $this->name = $terminal->name;
            $this->code = $terminal->code;
            $this->stock_location_id = $terminal->stock_location_id;
            $this->printer_connector = (string) $terminal->printer_connector;
            $this->receipt_printer = (string) $terminal->receipt_printer;
            $this->printer_paper_width = (string) ($terminal->printer_paper_width ?? 80);
            $this->is_active = $terminal->is_active;
        } else {
            Gate::authorize('create', Terminal::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('terminals', 'code')->ignore($this->terminal)],
            'stock_location_id' => ['required', Rule::exists('stock_locations', 'id')],
            'printer_connector' => ['nullable', Rule::in(['', Terminal::CONNECTOR_NETWORK, Terminal::CONNECTOR_WINDOWS, Terminal::CONNECTOR_CUPS])],
            'receipt_printer' => [
                'nullable', 'string', 'max:64',
                Rule::requiredIf($this->printer_connector !== ''),
                Rule::when($this->printer_connector === Terminal::CONNECTOR_NETWORK, [new ValidNetworkPrinterTarget]),
            ],
            'printer_paper_width' => ['required', 'in:58,80'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $validated['printer_connector'] = $validated['printer_connector'] ?: null;
        $validated['receipt_printer'] = $validated['receipt_printer'] ?: null;
        $validated['printer_paper_width'] = (int) $validated['printer_paper_width'];

        if ($this->terminal !== null) {
            Gate::authorize('update', $this->terminal);
            $this->terminal->update($validated);
        } else {
            Gate::authorize('create', Terminal::class);
            $this->terminal = Terminal::create($validated);
        }

        session()->flash('status', 'Terminal saved.');
        $this->redirectRoute('terminals.index');
    }

    public function render()
    {
        return view('livewire.sales.terminals.form', [
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'windowsPrinters' => app(WindowsPrinterDiscovery::class)->sharedQueues(),
        ]);
    }
}
