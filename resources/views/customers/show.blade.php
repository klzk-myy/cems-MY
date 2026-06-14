<x-app-layout title="Customer Details">
    <div class="p-6 space-y-6">
        <x-page-header title="Customer Details" :actions="true">
            {{ $customer->full_name ?? 'Customer Name' }}

            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('customers.edit', $customer ?? 1) }}">
                    Edit
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <x-card>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 rounded-full bg-canvas-subtle flex items-center justify-center text-xl font-bold">
                            {{ strtoupper(substr($customer->full_name ?? 'A', 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">{{ $customer->full_name ?? 'Ahmad bin Abu' }}</h2>
                            @php
                                $riskLevel = $customer->risk_level ?? 'medium';
                                if (is_object($riskLevel) && enum_exists(get_class($riskLevel))) {
                                    $riskLevel = $riskLevel->value;
                                }
                                $riskVariant = match (strtolower($riskLevel)) {
                                    'high' => 'danger',
                                    'medium' => 'warning',
                                    default => 'success',
                                };
                            @endphp
                            <x-badge :variant="$riskVariant">
                                {{ ucfirst($riskLevel) }} Risk
                            </x-badge>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-ink-muted">ID</span>
                            <p class="font-medium">{{ $customer->id_type ?? 'IC' }}: {{ $customer->id_number_masked ?? '****' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Nationality</span>
                            <p class="font-medium">{{ $customer->nationality ?? 'Malaysian' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Email</span>
                            <p class="font-medium">{{ $customer->email ?? 'ahmad@example.com' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Phone</span>
                            <p class="font-medium">{{ $customer->phone ?? '+60 12-345 6789' }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card title="CDD Status">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Level</span>
                            <span class="font-medium">{{ $customer->cdd_level ?? 'Standard' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Last Verified</span>
                            <span class="font-medium">{{ $customer->cdd_verified_at?->format('d M Y') ?? '15 Jan 2024' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Next Review</span>
                            <span class="font-medium">{{ $customer->cdd_expiry_at?->format('d M Y') ?? '15 Jan 2025' }}</span>
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <x-card>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Recent Transactions</h2>
                        <x-button variant="ghost" size="sm" href="#">View All</x-button>
                    </div>

                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @forelse($recentTransactions ?? [] as $transaction)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm">{{ $transaction->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $transaction->type }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $transaction->currency ?? 'USD' }}</td>
                                    <td class="px-4 py-3 text-sm">RM {{ number_format($transaction->amount, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge variant="success">Completed</x-badge>
                                    </td>
                                </tr>
                            @empty
                                <x-empty-state message="No recent transactions." :colspan="5" />
                            @endforelse
                        </x-slot:tbody>
                    </x-table>
                </x-card>

                <x-card title="Compliance Summary">
                    <x-stat-grid cols="4">
                        <x-stat-card label="Total Txns" :value="$stats['total_transactions'] ?? 24" />
                        <x-stat-card label="Total Value" value="RM {{ number_format($stats['total_value'] ?? 156750, 2) }}" />
                        <x-stat-card label="Alerts" :value="$stats['alerts'] ?? 0" />
                        <x-stat-card label="STRs Filed" :value="$stats['str_filed'] ?? 0" />
                    </x-stat-grid>
                </x-card>

                <x-card title="Notes">
                    <div class="space-y-3">
                        @forelse($notes ?? [] as $note)
                            <div class="p-3 bg-canvas-subtle rounded-lg">
                                <div class="text-sm">{{ $note->note }}</div>
                                <div class="text-xs text-ink-muted mt-1">{{ $note->created_at->format('d M Y h:i A') }} - {{ $note->creator?->name ?? 'System' }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No notes yet.</p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mt-4">
                        @csrf
                        <label for="note" class="sr-only">Add a note</label>
                        <textarea id="note" name="note" rows="2" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg mb-2" placeholder="Add a note..."></textarea>
                        <x-button type="submit" variant="primary" size="sm">Add Note</x-button>
                    </form>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
