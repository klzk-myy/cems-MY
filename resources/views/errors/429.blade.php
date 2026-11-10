<x-errors.layout title="429 - Error">
    <h1 class="text-4xl font-bold text-ink">429</h1>
    <p class="mt-2 text-ink-muted">Too many requests. Please try again later.</p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-block"><x-button variant="primary">Go to Dashboard</x-button></a>
</x-errors.layout>
