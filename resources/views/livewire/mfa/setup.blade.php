<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('mfa.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Setup Two-Factor Authentication</h1>

        <div class="bg-white rounded-lg shadow p-6">
            @if(!$verified)
            <div class="mb-6">
                <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Step 1: Scan the QR Code</h2>
                <p class="text-gray-600 mb-4">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
                <div class="bg-gray-100 p-4 inline-block rounded">
                    {!! $qrCode !!}
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Step 2: Enter Verification Code</h2>
                <p class="text-gray-600 mb-4">Enter the 6-digit code from your authenticator app</p>
                <input type="text" wire:model="code" maxlength="6" placeholder="000000" class="mt-1 block w-32 rounded border border-[var(--color-border)] px-3 py-2 text-center text-xl" />
                @error('code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end">
                <button wire:click="verify" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Verify & Enable</button>
            </div>
            @else
            <div class="p-4 bg-green-50 border border-green-200 rounded">
                <p class="text-green-800 font-medium">Two-factor authentication has been enabled successfully!</p>
            </div>
            <div class="mt-4 flex justify-end">
                <a href="{{ route('home') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Continue</a>
            </div>
            @endif
        </div>
    </div>
</div>