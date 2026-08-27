<x-app-layout title="Counters">
    <x-page-header title="Counters" description="Manage teller counters" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @for($i = 1; $i <= 6; $i++)
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-ink">Counter {{ $i }}</h3>
                        <p class="text-sm text-ink-muted">Teller {{ $i }}</p>
                    </div>
                    <x-badge variant="{{ $i % 2 === 0 ? 'success' : 'gray' }}">{{ $i % 2 === 0 ? 'Open' : 'Closed' }}</x-badge>
                </div>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('counters.open', $i) }}"><x-button variant="primary" size="sm">Open</x-button></a>
                    <a href="{{ route('counters.close.show', $i) }}"><x-button variant="secondary" size="sm">Close</x-button></a>
                </div>
            </x-card>
        @endfor
    </div>
</x-app-layout>
