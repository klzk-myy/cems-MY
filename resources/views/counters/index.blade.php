<x-app-layout title="Counters">
    <div class="space-y-6">
        <x-page-header title="Counters">
            <x-slot:actions>
                @can('create', \App\Models\Counter::class)
                    <x-button href="{{ route('counters.create') }}" variant="primary">New Counter</x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="3">
            <x-stat-card label="Total Counters" :value="$stats['total'] ?? 0" />
            <x-stat-card label="Open" :value="$stats['open'] ?? 0" color="green" />
            <x-stat-card label="Available" :value="$stats['available'] ?? 0" color="blue" />
        </x-stat-grid>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Counter</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($counters as $counter)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $counter->name }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($counter->sessions->count() > 0)
                                    <x-badge variant="success">Open</x-badge>
                                @else
                                    <x-badge variant="gray">Available</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($counter->sessions->count() === 0)
                                    <x-button variant="ghost" size="sm" href="{{ route('counters.open', $counter) }}">Open</x-button>
                                @else
                                    <div class="flex items-center gap-2">
                                        @php $openSession = $counter->sessions->first(); @endphp
                                        @if($openSession->session_date?->toDateString() !== now()->toDateString())
                                            <x-badge variant="warning">Since {{ $openSession->session_date->format('Y-m-d') }}</x-badge>
                                        @endif
                                        @if(auth()->user()->isManager())
                                            <x-button variant="ghost" size="sm" href="{{ route('counters.close.show', $counter) }}">Close</x-button>
                                        @endif
                                        <x-button variant="ghost" size="sm" href="{{ route('counters.handover.show', $counter) }}">Handover</x-button>
                                        <x-button variant="ghost" size="sm" href="{{ route('counters.history', $counter) }}">History</x-button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No counters found." :colspan="3" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
