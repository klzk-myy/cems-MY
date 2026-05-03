<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Stock Reconciliation</h1>

        <form wire:submit="reconcile" class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Select Location</label>
                <select wire:model="location" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                    <option value="">Select a location</option>
                    @foreach($locations as $loc)
                    <option value="{{ $loc }}">{{ $loc }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Product</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">System Qty</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Actual Qty</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                        <tr class="border-t border-[var(--color-border)]">
                            <td class="px-4 py-3">{{ $item['product_code'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $item['system_qty'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <input type="number" wire:model="actual_qty.{{ $index }}" class="w-24 text-right rounded border border-[var(--color-border)] px-2 py-1" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                @php $variance = ($item['actual_qty'] ?? $item['system_qty']) - $item['system_qty']; @endphp
                                <span class="{{ $variance !== 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $variance > 0 ? '+' : '' }}{{ $variance }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-center">Select a location to begin reconciliation</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Submit Reconciliation</button>
            </div>
        </form>
    </div>
</div>