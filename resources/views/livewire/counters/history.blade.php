<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Counter History</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Counter</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">User</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Opening</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Closing</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $session->opened_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">#{{ $session->counter->number }}</td>
                        <td class="px-4 py-3">{{ $session->user->name }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($session->opening_float, 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($session->closing_amount ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @php $variance = ($session->closing_amount ?? 0) - $session->opening_float - ($session->total_sales ?? 0); @endphp
                            <span class="{{ $variance !== 0 ? 'text-red-600' : 'text-green-600' }}">
                                ${{ number_format($variance, 2) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No history found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sessions->links() }}
        </div>
    </div>
</div>