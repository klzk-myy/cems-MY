<x-errors.layout title="500 - Error">
    <h1 class="text-4xl font-bold text-ink">500</h1>
    <p class="mt-2 text-ink-muted">Something went wrong on our end.</p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-block"><x-button variant="primary">Go to Dashboard</x-button></a>
</x-errors.layout>
