<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6 text-center">Verify Two-Factor Authentication</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-600 mb-4 text-center">Enter the 6-digit code from your authenticator app</p>

            <div class="mb-6">
                <input type="text" wire:model="code" maxlength="6" placeholder="000000" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-3 text-center text-2xl" />
                @error('code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            @if($error)
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                {{ $error }}
            </div>
            @endif

            <button wire:click="verify" class="w-full px-4 py-3 bg-[var(--color-ink)] text-white rounded font-medium">Verify</button>

            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">Back to login</a>
            </div>
        </div>
    </div>
</div>