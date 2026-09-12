@php
    $attributes = $attributes ?? new \Illuminate\View\ComponentAttributeBag([]);
@endphp

<x-app-layout title="MFA Setup" {{ $attributes }}>
    <x-page-header title="Setup Two-Factor Authentication" description="Two-factor authentication adds an extra layer of security to your account." />

    <div class="max-w-lg bg-surface rounded-xl border border-border shadow-sm p-5">
        @if(isset($qrCodeUrl))
        <div class="text-center mb-6">
            <img src="{{ $qrCodeUrl }}" alt="QR Code" class="mx-auto">
        </div>
        @endif

        @if(isset($secret))
        <div class="mb-6">
            <label class="block text-sm font-medium text-ink mb-1">Manual Entry Key</label>
            <code class="block bg-canvas-subtle p-2 rounded text-sm">{{ $secret }}</code>
        </div>
        @endif

        <form method="POST" action="{{ route('mfa.setup.store') }}">
            @csrf
            <x-input type="text" name="code" label="Verification Code" placeholder="Enter 6-digit code" maxlength="6" required />
            <x-button type="submit" variant="primary" class="w-full">Verify & Enable</x-button>
        </form>
    </div>
</x-app-layout>