<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockLocations;

use App\Domain\Inventory\Models\StockLocation;
use App\Support\Printing\Rules\ValidNetworkPrinterTarget;
use App\Support\Printing\WindowsPrinterDiscovery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?StockLocation $stockLocation = null;

    public string $name = '';

    public string $code = '';

    public bool $is_default = false;

    public bool $sells = true;

    public bool $receives = true;

    public string $label_printer_connector = '';

    public string $label_printer = '';

    public string $kitchen_printer_connector = '';

    public string $kitchen_printer = '';

    public function mount(?StockLocation $stockLocation = null): void
    {
        $this->stockLocation = $stockLocation;

        if ($stockLocation !== null) {
            Gate::authorize('update', $stockLocation);

            $this->name = $stockLocation->name;
            $this->code = $stockLocation->code;
            $this->is_default = $stockLocation->is_default;
            $this->sells = $stockLocation->sells;
            $this->receives = $stockLocation->receives;
            $this->label_printer_connector = (string) $stockLocation->label_printer_connector;
            $this->label_printer = (string) $stockLocation->label_printer;
            $this->kitchen_printer_connector = (string) $stockLocation->kitchen_printer_connector;
            $this->kitchen_printer = (string) $stockLocation->kitchen_printer;
        } else {
            Gate::authorize('create', StockLocation::class);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('stock_locations', 'code')->ignore($this->stockLocation?->id)],
            'is_default' => ['boolean'],
            'sells' => ['boolean'],
            'receives' => ['boolean'],
            'label_printer_connector' => ['nullable', Rule::in(['', StockLocation::CONNECTOR_NETWORK, StockLocation::CONNECTOR_WINDOWS, StockLocation::CONNECTOR_CUPS])],
            'label_printer' => [
                'nullable', 'string', 'max:64',
                Rule::requiredIf($this->label_printer_connector !== ''),
                Rule::when($this->label_printer_connector === StockLocation::CONNECTOR_NETWORK, [new ValidNetworkPrinterTarget]),
            ],
            'kitchen_printer_connector' => ['nullable', Rule::in(['', StockLocation::CONNECTOR_NETWORK, StockLocation::CONNECTOR_WINDOWS, StockLocation::CONNECTOR_CUPS])],
            'kitchen_printer' => [
                'nullable', 'string', 'max:64',
                Rule::requiredIf($this->kitchen_printer_connector !== ''),
                Rule::when($this->kitchen_printer_connector === StockLocation::CONNECTOR_NETWORK, [new ValidNetworkPrinterTarget]),
            ],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['code'] = Str::upper($validated['code']);
        $validated['label_printer_connector'] = $validated['label_printer_connector'] ?: null;
        $validated['label_printer'] = $validated['label_printer'] ?: null;
        $validated['kitchen_printer_connector'] = $validated['kitchen_printer_connector'] ?: null;
        $validated['kitchen_printer'] = $validated['kitchen_printer'] ?: null;

        DB::transaction(function () use ($validated) {
            if ($validated['is_default']) {
                StockLocation::where('is_default', true)
                    ->when($this->stockLocation, fn ($q) => $q->whereKeyNot($this->stockLocation->id))
                    ->update(['is_default' => false]);
            }

            if ($this->stockLocation !== null) {
                Gate::authorize('update', $this->stockLocation);
                $this->stockLocation->update($validated);
            } else {
                Gate::authorize('create', StockLocation::class);
                $this->stockLocation = StockLocation::create($validated);
            }
        });

        session()->flash('status', 'Stock location saved.');
        $this->redirectRoute('stock-locations.index');
    }

    public function render()
    {
        return view('livewire.inventory.stock-locations.form', [
            'windowsPrinters' => app(WindowsPrinterDiscovery::class)->sharedQueues(),
        ]);
    }
}
