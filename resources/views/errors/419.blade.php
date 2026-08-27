<x-errors.layout title="{{ 419 }} - Error">
    <h1 class="text-4xl font-bold text-ink">{{ 419 }}</h1>
    <p class="mt-2 text-ink-muted">
        @match(419)
            @when(403) You don't have permission to access this page.
            @when(404) The page you're looking for doesn't exist.
            @when(419) Your session has expired. Please try again.
            @when(429) Too many requests. Please try again later.
            @when(500) Something went wrong on our end.
            @when(503) Service temporarily unavailable.
        @endmatch
    </p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-block"><x-button variant="primary">Go to Dashboard</x-button></a>
</x-errors.layout>
