<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Edit Customer: {{ $customer->full_name }}</h2>
        </div>

        <form wire:submit="save" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" wire:model="fullName" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('fullName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                    <select wire:model="idType" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($idTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('idType') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                    <select wire:model="nationality" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($nationalities as $nation)
                            <option value="{{ $nation }}">{{ $nation }}</option>
                        @endforeach
                    </select>
                    @error('nationality') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" wire:model="phone" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" wire:model="email" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                    <input type="text" wire:model="occupation" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" wire:model="address" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="col-span-2 flex items-center gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="pepStatus" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">PEP (Politically Exposed Person)</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="isActive" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('customers.show', $customer) }}" class="px-4 py-2 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Update Customer
                </button>
            </div>
        </form>
    </div>
</div>