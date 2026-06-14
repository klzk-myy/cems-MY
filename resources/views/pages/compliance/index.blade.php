<x-app-layout title="Compliance Dashboard">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Compliance Dashboard</h1>

        <x-stat-grid :cols="4" class="mb-6">
            <div class="bg-surface rounded-lg shadow p-4">
                <div class="text-ink-muted text-sm">Open Flags</div>
                <div class="text-2xl font-bold text-yellow-600">{{ $stats['open'] ?? 0 }}</div>
            </div>
            <div class="bg-surface rounded-lg shadow p-4">
                <div class="text-ink-muted text-sm">Under Review</div>
                <div class="text-2xl font-bold text-blue-600">{{ $stats['under_review'] ?? 0 }}</div>
            </div>
            <div class="bg-surface rounded-lg shadow p-4">
                <div class="text-ink-muted text-sm">Resolved Today</div>
                <div class="text-2xl font-bold text-green-600">{{ $stats['resolved_today'] ?? 0 }}</div>
            </div>
            <div class="bg-surface rounded-lg shadow p-4">
                <div class="text-ink-muted text-sm">High Priority</div>
                <div class="text-2xl font-bold text-red-600">{{ $stats['high_priority'] ?? 0 }}</div>
            </div>
        </x-stat-grid>

        <div class="bg-surface rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b">
                <h2 class="text-lg font-semibold">Flagged Transactions</h2>
            </div>
            <table class="w-full">
                <thead class="bg-canvas-subtle">
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-2">Transaction</th>
                        <th class="px-4 py-2">Flag Type</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Created</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flags ?? [] as $flag)
                    <tr class="border-t hover:bg-canvas-subtle">
                        <td class="px-4 py-2 font-mono text-sm">{{ $flag->transaction_id }}</td>
                        <td class="px-4 py-2">{{ $flag->flag_type }}</td>
                        <td class="px-4 py-2">
                            <x-badge variant="{{ $flag->status === 'Open' ? 'warning' : ($flag->status === 'Under_Review' ? 'info' : 'success') }}">
                                {{ $flag->status }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-2">{{ $flag->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('compliance.flags.assign', $flag) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:underline">Assign to Me</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <x-empty-state message="No flagged transactions." :colspan="5" />
                    @endforelse
                </tbody>
            </table>
            {{ $flags->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>
