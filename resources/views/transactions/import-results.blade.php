<x-app-layout title="Import Results">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Import Results"
            description="Batch upload processing results"
        />

        <x-card title="Import Summary">
            <x-stat-grid cols="4">
                <x-stat-card label="Total Records" :value="$results['total'] ?? 0" />
                <x-stat-card label="Successful" :value="$results['successful'] ?? 0" color="green" />
                <x-stat-card label="Failed" :value="$results['failed'] ?? 0" color="red" />
                <x-stat-card label="Skipped" :value="$results['skipped'] ?? 0" color="yellow" />
            </x-stat-grid>
        </x-card>

        @if(!empty($results['errors']))
            <x-card title="Errors">
                <div class="space-y-4">
                    @foreach($results['errors'] as $error)
                        <x-alert type="error" class="mb-0" :title="'Row ' . ($error['row'] ?? 'N/A')">
                            {{ $error['message'] ?? 'Unknown error' }}
                        </x-alert>
                    @endforeach
                </div>
            </x-card>
        @endif

        @if(!empty($results['successful_records']))
            <x-card title="Successfully Imported">
                <div class="overflow-x-auto">
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Row</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Transaction ID</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @foreach($results['successful_records'] as $record)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm text-ink">{{ $record['row'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-badge :variant="($record['type'] ?? '') === 'Buy' ? 'success' : 'info'">
                                            {{ $record['type'] ?? 'N/A' }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink">{{ $record['amount'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-ink">{{ $record['currency'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-ink">{{ $record['transaction_id'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </x-slot:tbody>
                    </x-table>
                </div>
            </x-card>
        @endif

        <x-card>
            <div class="flex items-center gap-4">
                <x-button variant="primary" href="{{ route('transactions.batch-upload') }}">
                    Upload Another File
                </x-button>
                <x-button variant="secondary" href="{{ route('transactions.index') }}">
                    View All Transactions
                </x-button>
                @if(!empty($results['failed']))
                    <x-button variant="secondary" href="{{ route('transactions.batch-upload.download-errors', $results['batch_id'] ?? 0) }}">
                        Download Error Report
                    </x-button>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
