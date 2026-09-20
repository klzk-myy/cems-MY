<x-app-layout title="Chart of Accounts">
    <div class="space-y-6">
        <x-page-header title="Chart of Accounts" description="Read-only listing with balances as of {{ $asOfDate }}" />

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($accounts ?? [] as $account)
                        @php
                            $balance = $balances->get($account->account_code)['balance'] ?? '0';
                            $badgeVariant = match ($account->account_type) {
                                \App\Enums\AccountType::Asset => 'info',
                                \App\Enums\AccountType::Liability => 'warning',
                                \App\Enums\AccountType::Equity => 'purple',
                                \App\Enums\AccountType::Revenue => 'success',
                                \App\Enums\AccountType::Expense => 'danger',
                                default => 'gray',
                            };
                        @endphp
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-mono text-sm text-ink">{{ $account->account_code }}</td>
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $account->account_name }}
                                @unless($account->is_active)
                                    <x-badge variant="gray" size="sm">Inactive</x-badge>
                                @endunless
                            </td>
                            <td class="px-4 py-3">
                                <x-badge variant="{{ $badgeVariant }}">{{ $account->account_type->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ abs((float) $balance) > 0 ? 'text-ink' : 'text-ink-muted/50' }}">
                                {{ number_format((float) $balance, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <x-button href="{{ route('accounting.ledger.account', $account->account_code) }}" variant="ghost" size="sm">
                                    Ledger
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No accounts found." :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $accounts->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
