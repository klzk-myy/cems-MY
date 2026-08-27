<x-app-layout title="{{ ucfirst(str_replace('-', ' ', show)) }} Stock Transfer">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', show)) }} Stock Transfer" description="Inter-branch transfer management" />
    <x-card>
        <p class="text-ink-muted">Stock transfer {{ show }} view content.</p>
    </x-card>
</x-app-layout>
