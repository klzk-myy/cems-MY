<x-app-layout title="Emergency Counter Closure">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Emergency Counter Closure"
            description="Force close counter due to emergency situation"
        />

        <x-card class="max-w-2xl p-6">
            <x-alert type="error" title="Emergency Closure Notice">
                This action will immediately close the counter and surrender custody to management.
            </x-alert>

            <x-stat-grid cols="2" class="mb-6">
                <x-stat-card label="Counter" :value="$counter->name ?? 'Counter 1'" />
                <x-stat-card label="Current Operator" :value="$currentOperator ?? 'Unknown'" />
            </x-stat-grid>

            <form method="POST" action="{{ route('counters.emergency-close', $counter ?? 1) }}">
                @csrf

                <x-card-section title="Current Cash Holdings" class="mb-6">
                    <x-table>
                        <x-slot:thead></x-slot:thead>
                        <x-slot:tbody>
                            <tr>
                                <td class="text-ink-muted py-1">MYR Cash</td>
                                <td class="text-right font-medium">RM {{ number_format($holdings['MYR'] ?? 0, 2) }}</td>
                            </tr>
                            @foreach($currencies ?? ['USD', 'SGD', 'THB'] as $currency)
                            <tr>
                                <td class="text-ink-muted py-1">{{ $currency }}</td>
                                <td class="text-right font-medium">{{ number_format($holdings[$currency] ?? 0, 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="border-t border-border">
                                <td class="font-medium py-2">Total MYR Equivalent</td>
                                <td class="text-right font-semibold">RM {{ number_format($totalMYR ?? 0, 2) }}</td>
                            </tr>
                        </x-slot:tbody>
                    </x-table>
                </x-card-section>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Reason for Emergency Closure</label>
                    <textarea name="reason" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required></textarea>
                </div>

                <div class="mb-6">
                    <x-input
                        type="password"
                        name="manager_pin"
                        label="Manager Authorization PIN"
                        required
                        inline
                    />
                </div>

                <div class="flex gap-3">
                    <x-button type="submit" variant="danger">Confirm Emergency Closure</x-button>
                    <x-button variant="secondary" href="{{ route('counters.index') }}">Back</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
