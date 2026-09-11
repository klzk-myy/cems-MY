<x-app-layout title="Quarterly Large Value Report">
    <x-page-header title="Quarterly Large Value Report" description="BNM quarterly large value transaction report for {{ $quarter }}" />

    @if (isset($reportData['summary']))
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-stat-card label="Total Large Transactions" :value="$reportData['summary']['total_transactions'] ?? 0" color="blue" />
            <x-stat-card label="Total Value (MYR)" :value="'RM '.number_format((float) ($reportData['summary']['total_value'] ?? 0), 2)" color="green" />
            <x-stat-card label="Unique Customers" :value="$reportData['summary']['unique_customers'] ?? 0" color="purple" />
        </div>
    @endif

    <x-card class="mt-4 !p-0">
        @if (!empty($reportData['transactions']))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Date</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Customer</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Currency</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Amount (Foreign)</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Amount (MYR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($reportData['transactions'] as $txn)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3">{{ $txn['date'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $txn['customer'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $txn['type'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $txn['currency'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($txn['amount_foreign'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($txn['amount_myr'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No large value transactions found for this quarter." />
        @endif
    </x-card>

    @if ($reportGenerated)
        <x-card class="mt-4">
            <div class="flex items-center gap-2 text-sm text-ink-muted">
                <span class="inline-flex items-center rounded-full bg-success-subtle px-2.5 py-0.5 text-xs font-medium text-success-text">Generated</span>
                <span>Report generated on {{ $reportGenerated->created_at->format('Y-m-d H:i') }}</span>
            </div>
        </x-card>
    @endif
</x-app-layout>

