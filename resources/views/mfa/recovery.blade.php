<x-auth-layout title="MFA Recovery">
    <x-page-header title="MFA Recovery" description="Multi-factor authentication" />
    <x-card>
        <form method="POST" action="{{ route('mfa.recovery.verify') }}" class="space-y-4">
            @csrf
            <x-input name="recovery_code" label="Recovery Code" placeholder="Enter recovery code" :required="true" />
            <x-button type="submit" variant="primary" class="w-full">Verify</x-button>
        </form>
    </x-card>
</x-auth-layout>
