<div>
    @section('title', $stockLocation ? 'Edit stock location' : 'New stock location')

    <div class="max-w-lg rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                <input wire:model="name" id="name" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="code" class="mb-1 block text-sm font-medium">Code</label>
                <input wire:model="code" id="code" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm uppercase dark:border-gray-600 dark:bg-gray-800">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="is_default" type="checkbox" class="rounded border-gray-300">
                Default location
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="sells" type="checkbox" class="rounded border-gray-300">
                Sells
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="receives" type="checkbox" class="rounded border-gray-300">
                Receives
            </label>

            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                <h2 class="mb-3 text-sm font-semibold">Label printer</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="label_printer_connector" class="mb-1 block text-sm font-medium">Connection type</label>
                        <select wire:model.live="label_printer_connector" id="label_printer_connector" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Not configured</option>
                            <option value="network">Network (IP address)</option>
                            <option value="windows">Windows / SMB print queue</option>
                            <option value="cups">CUPS (Linux/macOS)</option>
                        </select>
                        @error('label_printer_connector') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($label_printer_connector !== '')
                    <div class="mt-4">
                        <label for="label_printer" class="mb-1 block text-sm font-medium">
                            {{ $label_printer_connector === 'network' ? 'Printer IP address (e.g. 192.168.1.60:9100)' : 'Print queue name' }}
                        </label>
                        @if ($label_printer_connector === 'windows' && $windowsPrinters !== [])
                            <select wire:model="label_printer" id="label_printer" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select a Windows printer&hellip;</option>
                                @foreach ($windowsPrinters as $shareName => $printerName)
                                    <option value="{{ $shareName }}">{{ $printerName }} ({{ $shareName }})</option>
                                @endforeach
                                @if ($label_printer !== '' && ! array_key_exists($label_printer, $windowsPrinters))
                                    <option value="{{ $label_printer }}">Current queue ({{ $label_printer }})</option>
                                @endif
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only Windows printers shared for raw printing are listed.</p>
                        @else
                            <input wire:model="label_printer" id="label_printer" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @endif
                        @error('label_printer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            @if (app(\App\Settings\BusinessProfileSettings::class)->business_type === \App\Settings\BusinessProfileSettings::BUSINESS_TYPE_RESTAURANT)
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <h2 class="mb-3 text-sm font-semibold">Kitchen printer</h2>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Shared by every register at this location &mdash; used by the register's "Send to kitchen" action in restaurant mode.</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="kitchen_printer_connector" class="mb-1 block text-sm font-medium">Connection type</label>
                            <select wire:model.live="kitchen_printer_connector" id="kitchen_printer_connector" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Not configured</option>
                                <option value="network">Network (IP address)</option>
                                <option value="windows">Windows / SMB print queue</option>
                                <option value="cups">CUPS (Linux/macOS)</option>
                            </select>
                            @error('kitchen_printer_connector') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($kitchen_printer_connector !== '')
                        <div class="mt-4">
                            <label for="kitchen_printer" class="mb-1 block text-sm font-medium">
                                {{ $kitchen_printer_connector === 'network' ? 'Printer IP address (e.g. 192.168.1.70:9100)' : 'Print queue name' }}
                            </label>
                            @if ($kitchen_printer_connector === 'windows' && $windowsPrinters !== [])
                                <select wire:model="kitchen_printer" id="kitchen_printer" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    <option value="">Select a Windows printer&hellip;</option>
                                    @foreach ($windowsPrinters as $shareName => $printerName)
                                        <option value="{{ $shareName }}">{{ $printerName }} ({{ $shareName }})</option>
                                    @endforeach
                                    @if ($kitchen_printer !== '' && ! array_key_exists($kitchen_printer, $windowsPrinters))
                                        <option value="{{ $kitchen_printer }}">Current queue ({{ $kitchen_printer }})</option>
                                    @endif
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only Windows printers shared for raw printing are listed.</p>
                            @else
                                <input wire:model="kitchen_printer" id="kitchen_printer" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @endif
                            @error('kitchen_printer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving&hellip;</span>
                </button>
                <a href="{{ route('stock-locations.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
            </div>
        </form>
    </div>
</div>
