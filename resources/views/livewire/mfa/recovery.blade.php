<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6 text-center">Recovery Codes</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-600 mb-4">Save these recovery codes in a safe place. You can use them to access your account if you lose access to your authenticator app.</p>

            <div class="bg-gray-100 p-4 rounded mb-6">
                <ul class="space-y-2">
                    @foreach($recoveryCodes as $code)
                    <li class="font-mono text-sm">{{ $code }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="flex justify-between items-center">
                <button wire:click="download" class="px-4 py-2 border border-[var(--color-border)] rounded">Download Codes</button>
                <a href="{{ route('home') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Done</a>
            </div>
        </div>
    </div>
</div>