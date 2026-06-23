<x-app-layout title="Cash Flow Statement">
    <div class="space-y-6">
        <x-page-header title="Cash Flow Statement" description="Cash flow analysis" />

        <x-card class="p-6">
            <p class="text-sm text-ink-muted">Cash flow statement generation is being implemented. Please check back later.</p>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-card class="p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Operating Activities</h3>
                <p class="mt-2 text-sm text-ink-muted/50">Cash flows from core business operations</p>
                <p class="mt-2 text-lg font-semibold text-ink-muted/50">RM 0.00</p>
            </x-card>

            <x-card class="p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Investing Activities</h3>
                <p class="mt-2 text-sm text-ink-muted/50">Cash flows from asset investments</p>
                <p class="mt-2 text-lg font-semibold text-ink-muted/50">RM 0.00</p>
            </x-card>

            <x-card class="p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Financing Activities</h3>
                <p class="mt-2 text-sm text-ink-muted/50">Cash flows from financing</p>
                <p class="mt-2 text-lg font-semibold text-ink-muted/50">RM 0.00</p>
            </x-card>
        </div>

        <x-card class="p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-ink">Net Cash Flow</p>
                <p class="text-2xl font-semibold text-ink-muted/50">RM 0.00</p>
            </div>
        </x-card>
    </div>
</x-app-layout>
