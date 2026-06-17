<x-app-layout title="Verify MFA">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Two-Factor Verification</h1>

        <div class="max-w-lg bg-surface rounded-lg shadow p-6">
            <p class="text-ink-muted mb-4">Enter the 6-digit code from your authenticator app.</p>

            <form method="POST" action="{{ route('mfa.verify.store') }}">
                @csrf
                <x-input type="text" name="code" label="Verification Code" placeholder="Enter 6-digit code" maxlength="6" required autofocus />
                <x-button type="submit" variant="primary" class="w-full">Verify</x-button>
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('mfa.recovery') }}" class="text-info hover:text-info-hover hover:underline">Use Recovery Code</a>
            </div>
        </div>
    </div>
</x-app-layout>