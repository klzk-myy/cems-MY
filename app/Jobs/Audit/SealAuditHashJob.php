<?php

namespace App\Jobs\Audit;

use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SealAuditHashJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * Backoff strategy for retries when unsealed predecessor entries are found.
     * Exponential backoff: 5s, 15s, 45s, 135s
     */
    public function backoff(): array
    {
        return [5, 15, 45, 135];
    }

    public int $timeout = 60;

    public function __construct(
        public int $logId
    ) {}

    public function handle(AuditService $auditService): void
    {
        DB::transaction(function () use ($auditService) {
            // Step 1: Lock the predecessor first (if exists) to ensure consistent lock ordering
            $predecessorId = SystemLog::where('id', '<', $this->logId)
                ->whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->value('id');

            $predecessor = null;
            if ($predecessorId) {
                $predecessor = SystemLog::where('id', $predecessorId)->lockForUpdate()->first();

                if (! $predecessor) {
                    throw new \RuntimeException("Predecessor log {$predecessorId} disappeared.");
                }

                // Guard: Check that no unsealed entries exist between predecessor and current entry.
                // If unsealed entries are found, the immediate predecessor has not been sealed yet,
                // and chaining to a farther predecessor would corrupt the hash chain (fork).
                // Release the transaction and retry with backoff to let the missing seal finish.
                $unsealedBetween = SystemLog::where('id', '>', $predecessorId)
                    ->where('id', '<', $this->logId)
                    ->whereNull('entry_hash')
                    ->exists();

                if ($unsealedBetween) {
                    throw new \RuntimeException(
                        "Unsealed entries exist between predecessor {$predecessorId} and target {$this->logId}. ".
                        'Retrying after intermediate entries are sealed.'
                    );
                }
            }

            // Step 2: Lock the target log entry and verify it's not already sealed
            $log = SystemLog::where('id', $this->logId)
                ->whereNull('entry_hash')
                ->lockForUpdate()
                ->first();

            if (! $log) {
                // Already sealed or deleted; nothing to do
                return;
            }

            // Get the predecessor's hash (already locked, so stable)
            $previousHash = $predecessor?->entry_hash ?? null;

            // Compute this entry's hash
            $entryHash = $auditService->computeEntryHash(
                $log->created_at->toIso8601String(),
                $log->user_id,
                $log->action,
                $log->entity_type,
                $log->entity_id,
                $previousHash
            );

            // Seal the entry
            $log->update([
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
            ]);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SealAuditHashJob failed permanently', [
            'log_id' => $this->logId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
