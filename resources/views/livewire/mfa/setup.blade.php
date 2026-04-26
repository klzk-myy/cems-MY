<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Set Up Authenticator App</h2>
        </div>

        <div class="p-6">
            <div class="text-center mb-6">
                <p class="text-gray-600 mb-4">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>

                <div class="inline-block p-4 bg-white border-2 border-gray-200 rounded-lg">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($otpauthUrl) }}" alt="QR Code" class="w-48 h-48">
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600 mb-2">Manual entry code:</p>
                <code class="text-xs font-mono text-gray-800 break-all">{{ $secret }}</code>
            </div>

            @if($error)
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    {{ $error }}
                </div>
            @endif

            <form wire:submit="verify" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                    <input type="text" wire:model="code" maxlength="6" placeholder="000000" class="w-full text-center text-2xl tracking-widest border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" autocomplete="one-time-code">
                    @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Verify and Enable MFA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>