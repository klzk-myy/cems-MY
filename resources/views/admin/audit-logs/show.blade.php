<x-app-layout title="{{ ucfirst(str_replace('/', ' ', $view)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', $view)) }}" description="Administration" />
    <x-card>
        <p class="text-ink-muted">{{ $view }} view content.</p>
    </x-card>
</x-app-layout>
