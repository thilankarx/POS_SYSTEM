<div>
    @section('title', $stockCount ? "Stock count {$stockCount->reference}" : 'New stock count')

    <div class="max-w-4xl space-y-4">
        @if ($stockCount)
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                    <span class="ml-2 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium dark:bg-gray-800">{{ ucfirst($stockCount->status) }}</span>
                    @if ($stockCount->is_blind)
                        <span class="ml-2 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300">Blind count</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if ($stockCount->status === 'counting')
                        <button wire:click="saveCounts" wire:loading.attr="disabled" wire:target="saveCounts" class="rounded-md bg-[#1b1b18] px-3 py-1.5 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]"><span wire:loading.remove wire:target="saveCounts">Save counts</span><span wire:loading wire:target="saveCounts">Saving&hellip;</span></button>
                        <button wire:click="submit" wire:confirm="Submit this count for review? Counted quantities cannot be changed afterwards." wire:loading.attr="disabled" wire:target="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:hover:bg-gray-800"><span wire:loading.remove wire:target="submit">Submit for review</span><span wire:loading wire:target="submit">Submitting&hellip;</span></button>
                    @endif
                    @if ($stockCount->status === 'review')
                        @can('approve', $stockCount)
                            <button wire:click="approve" wire:confirm="Approve this count and post variance to the inventory ledger?" wire:loading.attr="disabled" wire:target="approve" class="rounded-md bg-[#1b1b18] px-3 py-1.5 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]"><span wire:loading.remove wire:target="approve">Approve</span><span wire:loading wire:target="approve">Approving&hellip;</span></button>
                        @endcan
                    @endif
                    @if ($stockCount->status === 'approved')
                        @can('unapprove', $stockCount)
                            <button wire:click="unapprove" wire:confirm="Reverse this count's ledger postings and return it to review? This does not guarantee restoring exact pre-approval quantities if stock has moved since approval." wire:loading.attr="disabled" wire:target="unapprove" class="text-sm text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="unapprove">Unapprove</span><span wire:loading wire:target="unapprove">Unapproving&hellip;</span></button>
                        @endcan
                    @endif
                    @if (in_array($stockCount->status, ['draft', 'counting', 'review']))
                        <button wire:click="cancel" wire:confirm="Cancel this stock count?" wire:loading.attr="disabled" wire:target="cancel" class="text-sm text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="cancel">Cancel</span><span wire:loading wire:target="cancel">Cancelling&hellip;</span></button>
                    @endif
                </div>
            </div>
        @endif

        @error('lines') <p class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950">{{ $message }}</p> @enderror
        @error('counts') <p class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950">{{ $message }}</p> @enderror

        @if (! $stockCount)
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <form wire:submit="createDraft" class="space-y-4">
                    <div>
                        <label for="stock_location_id" class="mb-1 block text-sm font-medium">Location</label>
                        <select wire:model="stock_location_id" id="stock_location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Select&hellip;</option>
                            @foreach ($stockLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('stock_location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="is_blind" type="checkbox" class="rounded border-gray-300 dark:border-gray-600">
                        Blind count (hide expected quantity while counting)
                    </label>

                    <div>
                        <label for="note" class="mb-1 block text-sm font-medium">Note</label>
                        <textarea wire:model="note" id="note" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" wire:loading.attr="disabled" wire:target="createDraft" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                            <span wire:loading.remove wire:target="createDraft">Create</span>
                            <span wire:loading wire:target="createDraft">Creating&hellip;</span>
                        </button>
                        <a href="{{ route('stock-counts.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Back</a>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500 dark:text-gray-400">Location</span><br>{{ $stockCount->stockLocation?->name }}</div>
                    <div><span class="text-gray-500 dark:text-gray-400">Note</span><br>{{ $stockCount->note ?: '—' }}</div>
                </div>
            </div>

            @if ($stockCount->status === 'draft')
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-3 text-sm font-medium">Generate count lines</h3>

                    <label class="mb-3 flex items-center gap-2 text-sm">
                        <input wire:model.live="countAllItems" type="checkbox" class="rounded border-gray-300 dark:border-gray-600">
                        Count all items with stock at this location
                    </label>

                    @unless ($countAllItems)
                        <div class="mb-3 max-h-64 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-3 dark:border-gray-700">
                            @foreach ($items as $item)
                                <label class="flex items-center gap-2 text-sm">
                                    <input wire:model="selectedItemIds" value="{{ $item->id }}" type="checkbox" class="rounded border-gray-300 dark:border-gray-600">
                                    {{ $item->name }} ({{ $item->sku }})
                                </label>
                            @endforeach
                        </div>
                    @endunless

                    <button wire:click="generateLines" wire:loading.attr="disabled" wire:target="generateLines" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                        <span wire:loading.remove wire:target="generateLines">Generate lines</span>
                        <span wire:loading wire:target="generateLines">Generating&hellip;</span>
                    </button>
                </div>
            @endif

            @if ($stockCount->lines->isNotEmpty())
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Item</th>
                                @unless ($stockCount->is_blind && $stockCount->status === 'counting')
                                    <th class="px-4 py-2 text-right">Expected</th>
                                @endunless
                                <th class="px-4 py-2 text-right">Counted</th>
                                @if (in_array($stockCount->status, ['review', 'approved']))
                                    <th class="px-4 py-2 text-right">Variance</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stockCount->lines as $line)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2">{{ $line->item->name }} <span class="text-gray-400">({{ $line->item->sku }})</span></td>
                                    @unless ($stockCount->is_blind && $stockCount->status === 'counting')
                                        <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400">{{ $line->expected_quantity }}</td>
                                    @endunless
                                    <td class="px-4 py-2 text-right">
                                        @if ($stockCount->status === 'counting')
                                            <input wire:model="counts.{{ $line->id }}" type="text" inputmode="decimal" class="w-28 rounded-md border border-gray-300 px-2 py-1 text-right text-sm dark:border-gray-600 dark:bg-gray-800">
                                        @else
                                            {{ $line->counted_quantity ?? '—' }}
                                        @endif
                                    </td>
                                    @if (in_array($stockCount->status, ['review', 'approved']))
                                        <td class="px-4 py-2 text-right {{ (float) $line->variance !== 0.0 ? 'font-medium text-amber-600 dark:text-amber-400' : '' }}">{{ $line->variance }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
