<x-errors.layout title="404 - Error">
    <h1 class="text-4xl font-bold text-ink">404</h1>
    <p class="mt-2 text-ink-muted">The page you're looking for doesn't exist.</p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-block"><x-button variant="primary">Go to Dashboard</x-button></a>
</x-errors.layout>
