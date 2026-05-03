<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('accounting.journal.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Journal</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Create Journal Entry</h1>

        <form wire:submit="save" class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Date</label>
                <input type="date" wire:model="date" class="mt-1 block w-48 rounded border border-[var(--color-border)] px-3 py-2" />
                @error('date') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                <input type="text" wire:model="description" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                @error('description') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)] mb-4">Journal Lines</label>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Account</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Debit</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Credit</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $index => $line)
                        <tr>
                            <td class="px-4 py-2">
                                <select wire:model="lines.{{ $index }}.account_id" class="w-full rounded border border-[var(--color-border)] px-2 py-1">
                                    <option value="">Select account</option>
                                    @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.01" wire:model="lines.{{ $index }}.debit" class="w-full text-right rounded border border-[var(--color-border)] px-2 py-1" />
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.01" wire:model="lines.{{ $index }}.credit" class="w-full text-right rounded border border-[var(--color-border)] px-2 py-1" />
                            </td>
                            <td>
                                <button type="button" wire:click="removeLine({{ $index }})" class="text-red-600 hover:underline">Remove</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" wire:click="addLine" class="mt-2 text-sm text-blue-600 hover:underline">+ Add Line</button>
            </div>

            <div class="flex justify-between items-center">
                <div class="text-sm">
                    <span class="text-gray-500">Total Debit:</span> ${{ number_format($totalDebit, 2) }} |
                    <span class="text-gray-500">Total Credit:</span> ${{ number_format($totalCredit, 2) }}
                    @if($totalDebit != $totalCredit)
                        <span class="text-red-600 ml-2">不平衡</span>
                    @endif
                </div>
                <div class="flex gap-4">
                    <button type="submit" name="action" value="draft" class="px-4 py-2 border border-[var(--color-border)] rounded">Save as Draft</button>
                    <button type="submit" name="action" value="post" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Post Entry</button>
                </div>
            </div>
        </form>
    </div>
</div>