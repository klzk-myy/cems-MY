<x-app-layout title="Journal Entry">
    <div class="space-y-6">
        <x-page-header title="Journal Entry" description="Entry #{{ $entry['entry_no'] ?? 'JE-0001' }}">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.journal') }}">Back</x-button>
                @if(($entry['status'] ?? 'posted') === 'draft')
                    <x-button variant="primary">Edit</x-button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-ink-muted">Date</p>
                        <p class="mt-1 text-sm font-medium text-ink">{{ $entry['date'] ?? '2026-05-01' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Reference</p>
                        <p class="mt-1 text-sm font-medium text-ink">{{ $entry['reference'] ?? 'JE-0001' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Status</p>
                        <p class="mt-1">
                            <x-badge variant="success">Posted</x-badge>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Created By</p>
                        <p class="mt-1 text-sm font-medium text-ink">{{ $entry['created_by'] ?? 'Admin User' }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-sm text-ink-muted">Description</p>
                    <p class="mt-1 text-sm font-medium text-ink">{{ $entry['description'] ?? 'Currency revaluation gain' }}</p>
                </div>
            </div>
        </x-card>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">1100-001</td>
                        <td class="px-4 py-3 text-sm">Cash MYR</td>
                        <td class="px-4 py-3 text-sm">Currency revaluation</td>
                        <td class="px-4 py-3 text-sm text-right">500.00</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">7100-001</td>
                        <td class="px-4 py-3 text-sm">Revaluation Gain</td>
                        <td class="px-4 py-3 text-sm">Currency revaluation</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                        <td class="px-4 py-3 text-sm text-right">500.00</td>
                    </tr>
                    <tr class="bg-canvas-subtle">
                        <td colspan="3" class="px-4 py-3 text-sm font-medium text-ink">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">500.00</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">500.00</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Audit Trail">
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-border">
                    <div>
                        <p class="text-sm text-ink">Created</p>
                        <p class="text-xs text-ink-muted">Admin User</p>
                    </div>
                    <p class="text-sm text-ink-muted">2026-05-01 09:00:00</p>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-border">
                    <div>
                        <p class="text-sm text-ink">Posted</p>
                        <p class="text-xs text-ink-muted">Admin User</p>
                    </div>
                    <p class="text-sm text-ink-muted">2026-05-01 09:05:00</p>
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
