<x-app-layout title="Recovery Codes">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <x-card class="p-8">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-success-subtle mb-4">
                    <svg class="w-6 h-6 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <x-page-header
                    title="MFA Enabled Successfully"
                    description="Save these recovery codes in a safe place. You can use any of them to access your account if you lose your authenticator device."
                    class="text-center justify-center"
                />
            </div>

            <div class="bg-canvas-subtle rounded-lg p-6 mb-6">
                <div class="grid grid-cols-2 gap-4">
                    @foreach($recoveryCodes as $code)
                        <div class="font-mono text-sm text-ink bg-surface px-3 py-2 rounded border border-border text-center">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
            </div>

            <x-alert type="warning" title="Important Security Notice" :icon="true" class="mb-6">
                Each recovery code can only be used once. Store them securely and never share them with anyone.
            </x-alert>

            <div class="flex items-center justify-center">
                <x-button variant="primary" href="{{ route('dashboard') }}">Continue to Dashboard</x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
