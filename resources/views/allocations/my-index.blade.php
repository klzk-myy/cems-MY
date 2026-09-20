<x-app-layout title="My Stock Allocations">
    <div class="space-y-6">
        <x-page-header title="My Stock Allocations" description="Your currency custody and stock requests">
            <x-slot:actions>
                <x-button href="{{ route('my-allocations.request') }}">Request Stock</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <h3 class="text-sm font-semibold text-ink">Branch Pool</h3>
            <div class="mt-3">
                @include('allocations.partials.pool-summary')
            </div>
        </x-card>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Requested</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Allocated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Daily Limit (MYR)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Session Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Approver</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($allocations as $allocation)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3">{{ $allocation->id }}</td>
                            <td class="px-4 py-3">{{ $allocation->currency?->code ?? $allocation->currency_code }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $allocation->requested_quantity, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $allocation->allocated_quantity, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                {{ $allocation->current_quantity !== null ? number_format((float) $allocation->current_quantity, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($allocation->daily_limit_myr !== null)
                                    {{ number_format((float) $allocation->daily_used_myr, 2) }} / {{ number_format((float) $allocation->daily_limit_myr, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $allocation->session_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge variant="{{ $allocation->status->isActive() ? 'success' : ($allocation->status->isPending() || $allocation->status->isApproved() ? 'warning' : 'info') }}">
                                    {{ $allocation->status->label() }}
                                </x-badge>
                                @if($allocation->rejection_reason)
                                    <p class="mt-1 text-xs text-danger-text">{{ $allocation->rejection_reason }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $allocation->approver?->username ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex gap-2 justify-center">
                                    @if($allocation->isApproved())
                                        <form method="POST" action="{{ route('my-allocations.accept', $allocation->id) }}">
                                            @csrf
                                            <x-button type="submit" variant="success" size="sm">Accept</x-button>
                                        </form>
                                    @endif
                                    @if($allocation->isActive())
                                        <form method="POST" action="{{ route('my-allocations.return', $allocation->id) }}">
                                            @csrf
                                            <x-button type="submit" variant="secondary" size="sm">Return to Pool</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No allocations found." :colspan="10" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $allocations->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
