<x-auth-layout title="Verify MFA" {{ $attributes ?? '' }}>
    <x-page-header title="Verify MFA" description="Multi-factor authentication" />
    <x-card>
        <form method="POST" action="{{ route('mfa.verify.store') }}" class="space-y-4">
            @csrf
            <x-input name="code" label="Verification Code" placeholder="Enter 6-digit code" :required="true" />
            <x-button type="submit" variant="primary" class="w-full">Verify</x-button>
        </form>
        <p class="mt-4 text-center text-sm text-ink-muted">
            Lost your device? <a href="{{ route('mfa.recovery') }}" class="text-info hover:underline">Use recovery code</a>
        </p>
    </x-card>
</x-auth-layout>
