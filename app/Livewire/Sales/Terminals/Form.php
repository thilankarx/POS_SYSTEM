<?php

declare(strict_types=1);

namespace App\Livewire\Sales\Terminals;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Terminal;
use App\Support\Printing\Rules\ValidNetworkPrinterTarget;
use App\Support\Printing\Rules\ValidWindowsPrinterTarget;
use App\Support\Printing\WindowsPrinterDiscovery;
use App\Support\Printing\WindowsPrinterTarget;
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

    /**
     * Windows connector only: the PC the receipt printer (and the cash
     * drawer cabled to it) is plugged into. Blank means the server itself.
     * Stored inside receipt_printer as smb://HOST/Share.
     */
    public string $printer_host = '';

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

            if ($terminal->printer_connector === Terminal::CONNECTOR_WINDOWS && $terminal->receipt_printer !== null) {
                $target = WindowsPrinterTarget::split($terminal->receipt_printer);
                $this->printer_host = (string) $target['host'];
                $this->receipt_printer = $target['share'];
            }
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
                'nullable', 'string', 'max:90',
                Rule::requiredIf($this->printer_connector !== ''),
                Rule::when($this->printer_connector === Terminal::CONNECTOR_NETWORK, [new ValidNetworkPrinterTarget]),
                Rule::when($this->printer_connector === Terminal::CONNECTOR_WINDOWS, [new ValidWindowsPrinterTarget]),
            ],
            'printer_host' => [
                'nullable', 'string', 'max:63',
                Rule::when($this->printer_connector === Terminal::CONNECTOR_WINDOWS, ['regex:/^[\\\\\/]*[\w-]+(\.[\w-]+)*$/']),
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

        if ($validated['printer_connector'] === Terminal::CONNECTOR_WINDOWS && $validated['receipt_printer'] !== null) {
            $share = WindowsPrinterTarget::normalize($validated['receipt_printer']);

            // A full \\HOST\Share typed into the queue field wins over the host field.
            $validated['receipt_printer'] = str_starts_with($share, 'smb://')
                ? $share
                : WindowsPrinterTarget::compose($validated['printer_host'] ?? null, $share);
        }
        unset($validated['printer_host']);
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
