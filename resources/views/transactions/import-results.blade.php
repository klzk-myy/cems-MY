<x-app-layout title="Import Results">
    @php
        $isRunning = $import->status === App\Enums\TransactionImportStatus::Pending
            || $import->status === App\Enums\TransactionImportStatus::Processing;
    @endphp

    @if($isRunning)
        <meta http-equiv="refresh" content="5">
    @endif

    <div class="space-y-6">
        <x-page-header
            title="Import Results"
            description="Batch upload processing results"
        />

        @if(session('success'))
            <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if($isRunning)
            <div class="rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-200">
                Import is <strong>{{ $import->status->value }}</strong> —
                {{ $import->processed_rows }} / {{ $import->total_rows }} rows processed.
                This page refreshes automatically every 5 seconds…
            </div>
        @endif

        <x-card title="Import Summary">
            <x-stat-grid cols="4">
                <x-stat-card label="Total Records" :value="$import->total_rows ?? 0" />
                <x-stat-card label="Processed" :value="$import->processed_rows ?? 0" />
                <x-stat-card label="Successful" :value="$import->success_count ?? 0" color="green" />
                <x-stat-card label="Errors" :value="$import->error_count ?? 0" color="red" />
            </x-stat-grid>

            <div class="mt-4 text-sm space-y-1">
                <p><span class="text-ink-muted">File:</span> {{ $import->original_filename }}</p>
                <p><span class="text-ink-muted">Status:</span> <x-badge variant="{{ $import->getStatusColor() }}">{{ $import->status->value }}</x-badge></p>
                @if($import->completed_at)
                    <p><span class="text-ink-muted">Completed:</span> {{ $import->completed_at->format('d M Y H:i:s') }}</p>
                @endif
            </div>
        </x-card>

        @if($import->hasErrors())
            <x-card title="Errors">
                <div class="space-y-4">
                    @foreach($import->getErrors() as $error)
                        <x-alert type="error" class="mb-0" :title="'Row ' . ($error['row'] ?? 'N/A')">
                            {{ $error['error'] ?? 'Unknown error' }}
                        </x-alert>
                    @endforeach
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
                @if($import->hasErrors() && ! $isRunning)
                    <x-button variant="secondary" href="{{ route('transactions.batch-upload.download-errors', $import) }}">
                        Download Error Report
                    </x-button>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
