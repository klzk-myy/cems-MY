<x-app-layout title="{{ ucfirst(str_replace('-', ' ', create)) }} Stock Transfer">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', create)) }} Stock Transfer" description="Inter-branch transfer management" />
    <x-card>
        <p class="text-ink-muted">Stock transfer {{ create }} view content.</p>
    </x-card>
</x-app-layout>
