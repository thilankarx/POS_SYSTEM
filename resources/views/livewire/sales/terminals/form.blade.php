<div>
    @section('title', $terminal ? 'Edit terminal' : 'New terminal')

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                    <input wire:model="name" id="name" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="code" class="mb-1 block text-sm font-medium">Code</label>
                    <input wire:model="code" id="code" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="stock_location_id" class="mb-1 block text-sm font-medium">Stock location</label>
                <select wire:model="stock_location_id" id="stock_location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Select a location&hellip;</option>
                    @foreach ($stockLocations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('stock_location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                <h2 class="mb-3 text-sm font-semibold">Receipt printer</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="printer_connector" class="mb-1 block text-sm font-medium">Connection type</label>
                        <select wire:model.live="printer_connector" id="printer_connector" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Not configured</option>
                            <option value="network">Network (IP address)</option>
                            <option value="windows">Windows / SMB print queue</option>
                            <option value="cups">CUPS (Linux/macOS)</option>
                        </select>
                        @error('printer_connector') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="printer_paper_width" class="mb-1 block text-sm font-medium">Paper width</label>
                        <select wire:model="printer_paper_width" id="printer_paper_width" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="58">58mm</option>
                            <option value="80">80mm</option>
                        </select>
                        @error('printer_paper_width') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($printer_connector !== '')
                    <div class="mt-4">
                        <label for="receipt_printer" class="mb-1 block text-sm font-medium">
                            {{ $printer_connector === 'network' ? 'Printer IP address (e.g. 192.168.1.50:9100)' : 'Print queue name' }}
                        </label>
                        @if ($printer_connector === 'windows' && $windowsPrinters !== [])
                            <select wire:model="receipt_printer" id="receipt_printer" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select a Windows printer&hellip;</option>
                                @foreach ($windowsPrinters as $shareName => $printerName)
                                    <option value="{{ $shareName }}">{{ $printerName }} ({{ $shareName }})</option>
                                @endforeach
                                @if ($receipt_printer !== '' && ! array_key_exists($receipt_printer, $windowsPrinters))
                                    <option value="{{ $receipt_printer }}">Current queue ({{ $receipt_printer }})</option>
                                @endif
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only Windows printers shared for raw printing are listed.</p>
                        @else
                            <input wire:model="receipt_printer" id="receipt_printer" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @endif
                        @error('receipt_printer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="is_active" type="checkbox" class="rounded border-gray-300">
                Active
            </label>

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving&hellip;</span>
                </button>
                <a href="{{ route('terminals.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
            </div>
        </form>
    </div>
</div>
