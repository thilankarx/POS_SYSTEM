<div>
    @section('title', 'Void '.$sale->number)

    <div class="max-w-xl space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Void sale &mdash; {{ $sale->number }}</h2>
            <a href="{{ route('sales.show', $sale) }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Back to sale</a>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <dl class="mb-4 grid grid-cols-2 gap-2 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">Sold</dt>
                <dd>{{ $sale->sold_at?->format('Y-m-d H:i') }}</dd>
                <dt class="text-gray-500 dark:text-gray-400">Items</dt>
                <dd>{{ $sale->lines->count() }}</dd>
                <dt class="text-gray-500 dark:text-gray-400">Total</dt>
                <dd>{{ $sale->total }}</dd>
            </dl>

            <p class="mb-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                Voiding this sale will restore all stock it moved and mark every payment on it as voided. This
                cannot be undone.
            </p>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label for="reason" class="mb-1 block text-sm font-medium">Reason</label>
                    <textarea
                        wire:model="reason"
                        id="reason"
                        rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                        placeholder="e.g. rung up on the wrong customer"
                    ></textarea>
                    @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Void sale
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
