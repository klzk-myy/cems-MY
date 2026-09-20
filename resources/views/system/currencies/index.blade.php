<x-app-layout title="Currency Management">
    <div class="space-y-6">
        <x-page-header title="Currencies" description="Manage the currencies available for transactions and positions">
            <x-slot:actions>
                <x-button href="{{ route('system.currencies.create') }}" variant="primary">Add Currency</x-button>
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Symbol</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Decimal Places</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($currencies ?? [] as $currency)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-mono text-sm text-ink">{{ $currency->code }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $currency->name }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $currency->symbol ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $currency->decimal_places }}</td>
                            <td class="px-4 py-3">
                                @if($currency->is_active)
                                    <x-badge variant="success">Active</x-badge>
                                @else
                                    <x-badge variant="gray">Disabled</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-button href="{{ route('system.currencies.edit', $currency) }}" variant="ghost" size="sm">Edit</x-button>
                                    @if($currency->is_active)
                                        <form action="{{ route('system.currencies.disable', $currency) }}" method="POST"
                                              data-confirm="Disable {{ $currency->code }}? It will disappear from all form selects.">
                                            @csrf
                                            <x-button type="submit" variant="danger" size="sm">Disable</x-button>
                                        </form>
                                    @else
                                        <form action="{{ route('system.currencies.enable', $currency) }}" method="POST"
                                              data-confirm="Re-enable {{ $currency->code }}? It will appear in transaction form selects again.">
                                            @csrf
                                            <x-button type="submit" variant="ghost" size="sm">Enable</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No currencies found." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $currencies->links() }}</div>
        </x-card>

        <p class="text-sm text-ink-muted">
            Disabled currencies are hidden from all transaction forms but remain visible on historical records.
        </p>
    </div>
</x-app-layout>
