<x-app-layout title="Branch Pools">
    <div class="space-y-6">
        <x-page-header title="Branch Pools" description="Central pool funding and balances" />

        <x-card>
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th>Branch</th>
                        <th>Currency</th>
                        <th>Available</th>
                        <th>Allocated</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($pools as $pool)
                            <tr>
                                <td>{{ $pool->branch?->name }}</td>
                                <td>{{ $pool->currency_code }}</td>
                                <td>{{ number_format((float) $pool->available_balance, 4) }}</td>
                                <td>{{ number_format((float) $pool->allocated_balance, 4) }}</td>
                                <td>{{ number_format((float) ($pool->available_balance + $pool->allocated_balance), 4) }}</td>
                                <td><x-button href="{{ route('branch-pools.show', $pool->id) }}" variant="secondary">Manage</x-button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-ink-muted py-4">No pools configured.</td></tr>
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
        </x-card>
    </div>
</x-app-layout>
