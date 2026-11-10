<x-errors.layout title="503 - Error">
    <h1 class="text-4xl font-bold text-ink">503</h1>
    <p class="mt-2 text-ink-muted">Service temporarily unavailable.</p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-block"><x-button variant="primary">Go to Dashboard</x-button></a>
</x-errors.layout>
