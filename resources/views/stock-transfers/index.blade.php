<x-app-layout title="{{ ucfirst(str_replace('-', ' ', index)) }} Stock Transfer">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', index)) }} Stock Transfer" description="Inter-branch transfer management" />
    <x-card>
        <p class="text-ink-muted">Stock transfer {{ index }} view content.</p>
    </x-card>
</x-app-layout>
