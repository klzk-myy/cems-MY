<x-app-layout title="Import Results">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Import Results</h1>
            <p class="text-sm text-ink-muted mt-1">Batch upload processing results</p>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Import Summary</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Total Records</label>
                        <p class="text-2xl font-semibold text-ink">{{ $results['total'] ?? 0 }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Successful</label>
                        <p class="text-2xl font-semibold text-green-700">{{ $results['successful'] ?? 0 }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Failed</label>
                        <p class="text-2xl font-semibold text-red-700">{{ $results['failed'] ?? 0 }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Skipped</label>
                        <p class="text-2xl font-semibold text-yellow-700">{{ $results['skipped'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($results['errors']))
        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-red-700">Errors</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @foreach($results['errors'] as $error)
                        <div class="flex items-start gap-3 p-4 bg-red-50 rounded-lg">
                            <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-red-800">Row {{ $error['row'] ?? 'N/A' }}</p>
                                <p class="text-sm text-red-600">{{ $error['message'] ?? 'Unknown error' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @if(!empty($results['successful_records']))
        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Successfully Imported</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Row</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @foreach($results['successful_records'] as $record)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink">{{ $record['row'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($record['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $record['type'] ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $record['amount'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $record['currency'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $record['transaction_id'] ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="bg-surface border border-border rounded-xl">
            <div class="p-6">
                <div class="flex items-center gap-4">
                    <a
                        href="{{ route('transactions.batch-upload') }}"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover"
                    >
                        Upload Another File
                    </a>
                    <a
                        href="{{ route('transactions.index') }}"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle"
                    >
                        View All Transactions
                    </a>
                    @if(!empty($results['failed']))
                        <a
                            href="{{ route('transactions.batch-upload.download-errors', $results['batch_id'] ?? 0) }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle"
                        >
                            Download Error Report
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>