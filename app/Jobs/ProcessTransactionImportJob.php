<?php

namespace App\Jobs;

use App\Enums\TransactionImportStatus;
use App\Models\TransactionImport;
use App\Services\Transaction\TransactionImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Process a queued transaction batch import.
 *
 * Resumability: TransactionImportService::process() commits each row in its
 * own transaction and skips already-imported rows via idempotency_key, so if
 * the job dies mid-file it can simply be re-dispatched (or retried) and will
 * pick up where it left off without duplicating transactions.
 */
class ProcessTransactionImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public TransactionImport $import,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->import->id;
    }

    public function handle(TransactionImportService $importService): void
    {
        // A completed/failed import must not be reprocessed by a stale or
        // duplicated job instance.
        if (! in_array($this->import->status, [TransactionImportStatus::Pending, TransactionImportStatus::Processing], true)) {
            Log::info('ProcessTransactionImportJob: skipping import in non-queueable status', [
                'import_id' => $this->import->id,
                'status' => $this->import->status->value,
            ]);

            return;
        }

        $path = $this->resolveFilePath();

        if ($path === null) {
            $this->import->update([
                'status' => TransactionImportStatus::Failed->value,
                'error_details' => [['row' => 0, 'data' => [], 'error' => 'Import file is no longer available']],
                'completed_at' => now(),
            ]);

            return;
        }

        try {
            $importService->process($this->import, $path);
        } catch (\Throwable $e) {
            Log::error('ProcessTransactionImportJob: import failed', [
                'exception' => $e,
                'import_id' => $this->import->id,
            ]);

            $this->import->update([
                'status' => TransactionImportStatus::Failed->value,
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Resolve the stored CSV path. The controller persists the upload under
     * storage/app/imports before dispatching, so the queue worker reads it
     * from there.
     */
    protected function resolveFilePath(): ?string
    {
        $path = $this->import->filename;

        if ($path === null || $path === '') {
            return null;
        }

        if (Storage::exists($path)) {
            return Storage::path($path);
        }

        return file_exists($path) ? $path : null;
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['transaction-import', 'import-'.$this->import->id];
    }
}
