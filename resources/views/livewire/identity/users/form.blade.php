<div>
    @section('title', $user ? 'Edit user' : 'New user')

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="mb-1 block text-sm font-medium">First name</label>
                    <input wire:model="first_name" id="first_name" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="last_name" class="mb-1 block text-sm font-medium">Last name</label>
                    <input wire:model="last_name" id="last_name" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="phone" class="mb-1 block text-sm font-medium">Phone</label>
                <input wire:model="phone" id="phone" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="username" class="mb-1 block text-sm font-medium">Username</label>
                    <input wire:model="username" id="username" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input wire:model="email" id="email" type="email" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">
                        Password @if ($user) (leave blank to keep current) @endif
                    </label>
                    <input wire:model="password" id="password" type="password" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="role" class="mb-1 block text-sm font-medium">Role</label>
                    <select wire:model="role" id="role" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Select a role</option>
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption->name }}">{{ $roleOption->name }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="commission_rate" class="mb-1 block text-sm font-medium">Commission rate (%)</label>
                    <input wire:model="commission_rate" id="commission_rate" type="number" min="0" max="100" step="0.01" placeholder="e.g. 5.00" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('commission_rate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Earned on sales this user takes as the waiter. Leave blank for no commission.</p>
                </div>
            </div>

            <div>
                <span class="mb-1 block text-sm font-medium">Stock locations</span>
                <div class="flex flex-wrap gap-3">
                    @foreach ($stockLocations as $location)
                        <label class="flex items-center gap-1 text-sm">
                            <input wire:model="stock_location_ids" type="checkbox" value="{{ $location->id }}" class="rounded border-gray-300 dark:border-gray-600">
                            {{ $location->name }}
                        </label>
                    @endforeach
                </div>
                @error('stock_location_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Where this user can open a shift, pick a terminal, and operate the register.</p>
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
                <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
            </div>
        </form>
    </div>
</div>
