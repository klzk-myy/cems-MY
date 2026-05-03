<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Journal Entries</h1>
            <a href="{{ route('accounting.journal.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Entry</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Entry #</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Description</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Debit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Credit</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $entry->date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $entry->entry_number }}</td>
                        <td class="px-4 py-3">{{ Str::limit($entry->description, 40) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($entry->debit_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($entry->credit_total, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($entry->is_posted)
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Posted</span>
                            @else
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Draft</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No journal entries found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $entries->links() }}
        </div>
    </div>
</div>