<x-errors.layout title="403 - Error">
    <h1 class="text-4xl font-bold text-ink">403</h1>
    <p class="mt-2 text-ink-muted">You don't have permission to access this page.</p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-block"><x-button variant="primary">Go to Dashboard</x-button></a>
</x-errors.layout>
