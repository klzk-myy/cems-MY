<div>
    <div class="bg-white rounded-xl shadow-sm border border-[--color-border]">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h2 class="text-lg font-semibold text-[--color-ink]">Reset Password: {{ $user->username }}</h2>
        </div>

        <form wire:submit="resetPasswordAction" class="p-6 space-y-6">
            <div class="max-w-md">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">New Password</label>
                    <input type="password" wire:model="password" class="w-full border-[--color-border] rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    <span class="text-xs text-gray-500">Min 12 chars with uppercase, lowercase, number, special char</span>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Confirm Password</label>
                    <input type="password" wire:model="passwordConfirmation" class="w-full border-[--color-border] rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('password_confirmation') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-[--color-border]">
                <a href="{{ route('users.show', $user) }}" class="px-4 py-2 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Reset Password
                </button>
            </div>
        </form>
    </div>
</div>
