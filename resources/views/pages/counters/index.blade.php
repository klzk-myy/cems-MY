<x-app-layout title="Counters">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Counters</h1>

        <x-stat-grid :cols="3" class="mb-6">
            <div class="bg-surface border border-border rounded-xl p-4">
                <div class="text-ink-muted text-sm">Total Counters</div>
                <div class="text-2xl font-bold">{{ $stats['total'] ?? 0 }}</div>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <div class="text-ink-muted text-sm">Open</div>
                <div class="text-2xl font-bold text-green-600">{{ $stats['open'] ?? 0 }}</div>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <div class="text-ink-muted text-sm">Available</div>
                <div class="text-2xl font-bold text-blue-600">{{ $stats['available'] ?? 0 }}</div>
            </div>
        </x-stat-grid>

        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-canvas-subtle">
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3">Counter</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counters as $counter)
                    <tr class="border-t hover:bg-canvas-subtle">
                        <td class="px-4 py-3">{{ $counter->name }}</td>
                        <td class="px-4 py-3">
                            @if($counter->sessions->count() > 0)
                                <x-badge variant="success">Open</x-badge>
                            @else
                                <x-badge variant="gray">Available</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($counter->sessions->count() === 0)
                                <a href="{{ route('counters.open', $counter) }}" class="text-blue-600 hover:underline">Open</a>
                            @else
                                <a href="{{ route('counters.history', $counter) }}" class="text-ink-muted hover:underline">History</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <x-empty-state message="No counters found." :colspan="3" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>