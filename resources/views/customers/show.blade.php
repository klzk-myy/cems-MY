<x-app-layout title="Customer Details">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Customer Details</h1>
                <p class="text-ink-muted text-sm mt-1">{{ $customer->full_name ?? 'Customer Name' }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('customers.edit', $customer ?? 1) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Edit
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-xl font-bold">
                            {{ strtoupper(substr($customer->full_name ?? 'A', 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">{{ $customer->full_name ?? 'Ahmad bin Abu' }}</h2>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                                @if(($customer->risk_level ?? '') === 'high') bg-red-100 text-red-700
                                @elseif(($customer->risk_level ?? '') === 'medium') bg-yellow-100 text-yellow-700
                                @else bg-green-100 text-green-700 @endif">
                                {{ ucfirst($customer->risk_level ?? 'medium') }} Risk
                            </span>
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
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h3 class="text-sm font-semibold mb-3">CDD Status</h3>
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
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Recent Transactions</h2>
                        <a href="#" class="text-blue-600 hover:underline text-sm">View All</a>
                    </div>

                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-ink-muted border-b">
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Type</th>
                                <th class="pb-3">Currency</th>
                                <th class="pb-3">Amount</th>
                                <th class="pb-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions ?? [] as $transaction)
                            <tr class="border-b">
                                <td class="py-3 text-sm">{{ $transaction->created_at->format('d M Y') }}</td>
                                <td class="py-3 text-sm">{{ $transaction->type }}</td>
                                <td class="py-3 text-sm">{{ $transaction->currency ?? 'USD' }}</td>
                                <td class="py-3 text-sm">RM {{ number_format($transaction->amount, 2) }}</td>
                                <td class="py-3">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-ink-muted">No recent transactions.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h2 class="text-lg font-semibold mb-4">Compliance Summary</h2>
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-canvas-subtle rounded-lg">
                            <div class="text-2xl font-bold">{{ $stats['total_transactions'] ?? 24 }}</div>
                            <div class="text-xs text-ink-muted">Total Txns</div>
                        </div>
                        <div class="text-center p-4 bg-canvas-subtle rounded-lg">
                            <div class="text-2xl font-bold">RM {{ number_format($stats['total_value'] ?? 156750, 2) }}</div>
                            <div class="text-xs text-ink-muted">Total Value</div>
                        </div>
                        <div class="text-center p-4 bg-canvas-subtle rounded-lg">
                            <div class="text-2xl font-bold">{{ $stats['alerts'] ?? 0 }}</div>
                            <div class="text-xs text-ink-muted">Alerts</div>
                        </div>
                        <div class="text-center p-4 bg-canvas-subtle rounded-lg">
                            <div class="text-2xl font-bold">{{ $stats['str_filed'] ?? 0 }}</div>
                            <div class="text-xs text-ink-muted">STRs Filed</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h2 class="text-lg font-semibold mb-4">Notes</h2>
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
                        <textarea id="note" name="note" rows="2" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg mb-2" placeholder="Add a note..."></textarea>
                        <x-button type="submit" variant="primary" size="sm">Add Note</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>