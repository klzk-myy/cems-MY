<x-app-layout title="{{ ucfirst(str_replace('-', ' ', $view)) }} MFA">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', $view)) }} MFA" description="Multi-factor authentication" />
    <x-card>
        <p class="text-ink-muted">MFA {{ $view }} view content.</p>
    </x-card>
</x-app-layout>
