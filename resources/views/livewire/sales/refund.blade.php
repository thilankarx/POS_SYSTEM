<div>
    @section('title', 'Return '.$sale->number)

    <div class="max-w-3xl space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Process a return &mdash; {{ $sale->number }}</h2>
            <a href="{{ route('sales.show', $sale) }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Back to sale</a>
        </div>

        @error('lines') <p class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950">{{ $message }}</p> @enderror

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form wire:submit="save" class="space-y-4">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="py-2">Item</th>
                            <th class="py-2 text-right">Sold</th>
                            <th class="py-2 text-right">Already returned</th>
                            <th class="py-2 text-right">Return qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->returnableLines() as $line)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2">{{ $line->item_name }} <span class="text-gray-500 dark:text-gray-400">({{ $line->sku }})</span></td>
                                <td class="py-2 text-right">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
                                <td class="py-2 text-right">{{ rtrim(rtrim((string) $line->quantity_returned, '0'), '.') }}</td>
                                <td class="py-2 text-right">
                                    <input
                                        wire:model="quantities.{{ $line->id }}"
                                        type="text"
                                        inputmode="decimal"
                                        class="w-24 rounded-md border border-gray-300 px-2 py-1 text-right text-sm dark:border-gray-600 dark:bg-gray-800"
                                    >
                                    @error("quantities.{$line->id}") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="grid grid-cols-2 gap-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div>
                        <label for="return_reason_id" class="mb-1 block text-sm font-medium">Reason</label>
                        <select wire:model="return_reason_id" id="return_reason_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Select a reason&hellip;</option>
                            @foreach ($returnReasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->name }} @unless ($reason->restocks) (does not restock) @endunless</option>
                            @endforeach
                        </select>
                        @error('return_reason_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="refund_payment_method_id" class="mb-1 block text-sm font-medium">Refund method</label>
                        <select wire:model="refund_payment_method_id" id="refund_payment_method_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Select a method&hellip;</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                        @error('refund_payment_method_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black dark:bg-white dark:text-[#1b1b18]">
                        Process return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
