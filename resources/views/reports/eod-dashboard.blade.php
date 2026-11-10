<x-app-layout title="EOD Reconciliation">
    <div class="space-y-6">
        <x-page-header title="EOD Reconciliation" description="End-of-Day reconciliation dashboard for managers">
            <x-slot:actions>
                <form method="GET" action="{{ route('eod.dashboard') }}" class="flex gap-2 items-center">
                    <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="rounded-md border-border bg-surface text-ink text-sm">
                    <x-button type="submit" variant="primary">View</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <x-card>
                <div class="text-sm text-ink-muted">Total Counters</div>
                <div class="text-2xl font-bold mt-1">{{ $report['summary']['total_counters'] ?? 0 }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-ink-muted">Active</div>
                <div class="text-2xl font-bold mt-1 text-success">{{ $report['summary']['active_counters'] ?? 0 }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-ink-muted">Closed</div>
                <div class="text-2xl font-bold mt-1 text-info">{{ $report['summary']['closed_counters'] ?? 0 }}</div>
            </x-card>
            <x-card>
                <div class="text-sm text-ink-muted">Handed Over</div>
                <div class="text-2xl font-bold mt-1 text-warning">{{ $report['summary']['handed_over_counters'] ?? 0 }}</div>
            </x-card>
        </div>

        <x-card title="Cash Flow Totals">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th scope="col" class="text-left py-2 px-4">Description</th>
                            <th scope="col" class="text-right py-2 px-4">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-border">
                            <td class="py-2 px-4">Opening Float</td>
                            <td class="py-2 px-4 text-right">{{ number_format((float) ($report['totals']['opening_float'] ?? 0), 2) }}</td>
                        </tr>
                        <tr class="border-b border-border">
                            <td class="py-2 px-4">Cash Received</td>
                            <td class="py-2 px-4 text-right">{{ number_format((float) ($report['totals']['cash_received'] ?? 0), 2) }}</td>
                        </tr>
                        <tr class="border-b border-border">
                            <td class="py-2 px-4">Cash Paid Out</td>
                            <td class="py-2 px-4 text-right">{{ number_format((float) ($report['totals']['cash_paid_out'] ?? 0), 2) }}</td>
                        </tr>
                        <tr class="border-b border-border font-bold">
                            <td class="py-2 px-4">Closing Float (Expected)</td>
                            <td class="py-2 px-4 text-right">{{ number_format((float) ($report['totals']['closing_expected'] ?? 0), 2) }}</td>
                        </tr>
                        <tr class="border-b border-border font-bold">
                            <td class="py-2 px-4">Closing Float (Actual)</td>
                            <td class="py-2 px-4 text-right">{{ number_format((float) ($report['totals']['closing_actual'] ?? 0), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>

        @php
            $variance = (string) ($report['totals']['variance'] ?? '0');
            $absVariance = ltrim($variance, '-');
            $redThreshold = (string) ($varianceRed ?? '500.00');
            $yellowThreshold = (string) ($varianceYellow ?? '100.00');

            if (bccomp($absVariance, $redThreshold, 2) === 1) {
                $alertClass = 'bg-danger-subtle border-danger-border text-danger-text';
                $varianceLabel = 'CRITICAL VARIANCE';
            } elseif (bccomp($absVariance, $yellowThreshold, 2) === 1) {
                $alertClass = 'bg-warning-subtle border-warning-border text-warning-text';
                $varianceLabel = 'Variance Warning';
            } elseif (bccomp($variance, '0', 2) !== 0) {
                $alertClass = 'bg-warning-subtle border-warning-border text-warning-text';
                $varianceLabel = 'Minor Variance';
            } else {
                $alertClass = 'bg-success-subtle border-success-border text-success-text';
                $varianceLabel = 'No Variance';
            }
        @endphp
        <x-card>
            <div class="p-4 rounded-lg border {{ $alertClass }}">
                <strong>{{ $varianceLabel }}</strong>
                @if(bccomp($variance, '0', 2) !== 0)
                    — RM {{ number_format((float) $variance, 2) }}
                @endif
            </div>
        </x-card>

        @if(!empty($report['counter_summaries']))
            <x-card title="Counter Details">
                <div class="overflow-x-auto">
                    <x-table>
                        <x-slot:thead>
                            <th scope="col">Counter</th>
                            <th scope="col">Status</th>
                            <th scope="col">Teller</th>
                            <th scope="col">Opening Float</th>
                            <th scope="col">Closing Expected</th>
                            <th scope="col">Closing Actual</th>
                            <th scope="col">Variance</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @foreach($report['counter_summaries'] as $counter)
                                <tr>
                                    <td>{{ $counter['counter_name'] ?? 'N/A' }}</td>
                                    <td>
                                        <x-badge variant="{{ ($counter['session']['status'] ?? '') === \App\Enums\CounterSessionStatus::Closed->value ? 'success' : 'warning' }}">
                                            {{ $counter['session']['status'] ?? 'Unknown' }}
                                        </x-badge>
                                    </td>
                                    <td>{{ $counter['session']['current_user']['name'] ?? $counter['session']['opened_by']['name'] ?? 'N/A' }}</td>
                                    <td>{{ number_format((float) ($counter['opening_float'] ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) ($counter['closing_float_expected'] ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) ($counter['closing_float_actual'] ?? 0), 2) }}</td>
                                    <td class="{{ bccomp((string) ($counter['variance'] ?? '0'), '0', 2) !== 0 ? 'text-danger font-bold' : '' }}">
                                        {{ number_format((float) ($counter['variance'] ?? 0), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:tbody>
                    </x-table>
                </div>
            </x-card>
        @else
            <x-card>
                <p class="text-center text-ink-muted py-8">No counter sessions found for {{ $date->format('Y-m-d') }}.</p>
            </x-card>
        @endif
    </div>
</x-app-layout>
