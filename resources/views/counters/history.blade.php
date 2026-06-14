<x-app-layout title="Counter History">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Counter History</h1>
                <p class="text-ink-muted text-sm mt-1">{{ $counter->name ?? 'Counter 1' }} - Session records and transactions</p>
            </div>
            <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                Back to Counters
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-surface border border-border rounded-xl p-4">
                <div class="text-ink-muted text-sm">Total Sessions</div>
                <div class="text-2xl font-bold">{{ $stats['total_sessions'] ?? 0 }}</div>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <div class="text-ink-muted text-sm">Total Transactions</div>
                <div class="text-2xl font-bold">{{ $stats['total_transactions'] ?? 0 }}</div>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <div class="text-ink-muted text-sm">Total Value</div>
                <div class="text-2xl font-bold">RM {{ number_format($stats['total_value'] ?? 0, 2) }}</div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-canvas-subtle">
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Operator</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Opening Float</th>
                        <th class="px-4 py-3">Closing Float</th>
                        <th class="px-4 py-3">Variance</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions ?? [] as $session)
                    <tr class="border-t hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">
                            {{ $session->opened_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $session->user->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $session->type ?? 'Regular' }}</td>
                        <td class="px-4 py-3 text-sm">RM {{ number_format($session->opening_float ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($session->closing_float)
                                RM {{ number_format($session->closing_float, 2) }}
                            @else
                                <span class="text-ink-muted/50">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($session->variance)
                                <span class="{{ $session->variance != 0 ? 'text-red-600' : 'text-green-600' }}">
                                    RM {{ number_format($session->variance, 2) }}
                                </span>
                            @else
                                <span class="text-ink-muted/50">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($session->closed_at)
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-gray-700">Closed</span>
                            @else
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-ink-muted">No session history found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sessions->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>