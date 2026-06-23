<x-app-layout title="MFA Recovery">
    <div class="max-w-lg mx-auto p-6 space-y-6">
        <x-page-header
            title="Account Recovery"
            description="Recover access to your account using recovery codes"
        />

        <x-card title="Use Recovery Code" description="Enter one of your saved recovery codes">
            <div class="p-6">
                @if(isset($error))
                    <x-alert type="error">{{ $error }}</x-alert>
                @endif

                <form method="POST" action="{{ route('mfa.recovery.verify') }}">
                    @csrf

                    <div class="space-y-4">
                        <x-input type="text" name="recovery_code" label="Recovery Code" class="font-mono tracking-widest" placeholder="XXXX-XXXX-XXXX" required inline />
                        <x-input type="password" name="password" label="Password" required inline />
                    </div>

                    <x-button type="submit" variant="primary" class="w-full mt-6">Verify Recovery Code</x-button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-ink-muted mb-2">Don't have recovery codes?</p>
                    <a href="{{ route('mfa.setup') }}" class="text-sm text-info-text hover:text-info">Set up new authentication</a>
                </div>

                @if(isset($remainingCodes) && $remainingCodes > 0)
                    <x-alert type="info" class="mt-6" :icon="false">
                        <strong>{{ $remainingCodes }}</strong> recovery codes remaining. Store them safely.
                    </x-alert>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
