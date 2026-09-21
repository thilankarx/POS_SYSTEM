<div>
    @section('title', 'Dinner Tables')

    <div class="mb-4 flex items-center justify-between gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name&hellip;" class="w-64 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">

        @can('create', \App\Domain\Sales\Models\DinnerTable::class)
            <a href="{{ route('dinner-tables.create') }}" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black dark:bg-white dark:text-[#1b1b18]">
                New table
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Location</th>
                    <th class="px-4 py-2">Seats</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-2">{{ $table->name }}</td>
                        <td class="px-4 py-2">{{ $table->stockLocation?->name }}</td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $table->seats }}</td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $table->status === 'occupied' ? 'Occupied' : 'Available' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('dinner-tables.edit', $table) }}" class="text-sm text-gray-600 hover:underline dark:text-gray-300">Edit</a>
                            @can('delete', $table)
                                <button wire:click="delete({{ $table->id }})" wire:confirm="Delete this table?" class="ml-3 text-sm text-red-600 hover:underline">Delete</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No dinner tables yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tables->links() }}
    </div>
</div>
