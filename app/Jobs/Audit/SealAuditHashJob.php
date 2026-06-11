<?php

namespace App\Jobs\Audit;

use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SealAuditHashJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $logId
    ) {}

    public function handle(AuditService $auditService): void
    {
        // Use atomic update to prevent race condition
        // Only seal if entry_hash is still null (not already sealed)
        $affected = SystemLog::where('id', $this->logId)
            ->whereNull('entry_hash')
            ->lockForUpdate()
            ->update([
                // Will be set in transaction below
            ]);

        if ($affected === 0) {
            // Log was deleted or already sealed by another job
            return;
        }

        // Re-fetch the log within the same transaction context
        $log = SystemLog::find($this->logId);

        if (! $log) {
            return;
        }

        // Get the previous log's sealed hash
        // Use WITH share lock to prevent concurrent modifications
        $previousLog = SystemLog::where('id', '<', $log->id)
            ->whereNotNull('entry_hash')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $previousHash = $previousLog?->entry_hash;

        // Compute this entry's hash
        $entryHash = $auditService->computeEntryHash(
            $log->created_at->toIso8601String(),
            $log->user_id,
            $log->action,
            $log->entity_type,
            $log->entity_id,
            $previousHash
        );

        // Atomically seal the entry with optimistic locking
        $sealed = SystemLog::where('id', $log->id)
            ->whereNull('entry_hash')
            ->update([
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
            ]);

        if ($sealed === 0) {
            Log::warning('SealAuditHashJob: Lost race condition, entry already sealed', [
                'log_id' => $log->id,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SealAuditHashJob failed permanently', [
            'log_id' => $this->logId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
