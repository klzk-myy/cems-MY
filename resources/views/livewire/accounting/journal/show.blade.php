<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('accounting.journal.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Journal</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Journal Entry #{{ $entry->entry_number }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Date</label>
                    <p class="mt-1">{{ $entry->date->format('Y-m-d') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Status</label>
                    <p class="mt-1">
                        @if($entry->is_posted)
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Posted</span>
                        @else
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Draft</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Created By</label>
                    <p class="mt-1">{{ $entry->user->name }}</p>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                <p class="mt-1">{{ $entry->description }}</p>
            </div>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Account</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Debit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->lines as $line)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $line->account->code }} - {{ $line->account->name }}</td>
                        <td class="px-4 py-3 text-right">{{ $line->debit > 0 ? '$'.number_format($line->debit, 2) : '' }}</td>
                        <td class="px-4 py-3 text-right">{{ $line->credit > 0 ? '$'.number_format($line->credit, 2) : '' }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-medium">
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right">${{ number_format($entry->debit_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($entry->credit_total, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            @if(!$entry->is_posted)
            <div class="flex justify-end gap-4 mt-6">
                <button wire:click="delete" wire:confirm="Delete this entry?" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
                <button wire:click="post" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Post Entry</button>
            </div>
            @endif
        </div>
    </div>
</div>