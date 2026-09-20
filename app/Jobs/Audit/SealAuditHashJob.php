<?php

namespace App\Jobs\Audit;

use App\Exceptions\Domain\AuditIntegrityException;
use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SealAuditHashJob implements ShouldQueue, ShouldQueueAfterCommit
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
            // A missing row means this job was picked up before the writing
            // transaction committed (a queue connector without after_commit)
            // or the row was hard-deleted. Throw so the backoff ladder retries —
            // returning silently would leave a committed entry permanently
            // unsealed, indistinguishable from the already-sealed case below.
            if (! SystemLog::where('id', $this->logId)->exists()) {
                throw new AuditIntegrityException("Audit log {$this->logId} is not visible yet; retrying.");
            }

            // Step 1: Lock the predecessor first (if exists) to ensure consistent lock ordering
            $predecessorId = SystemLog::where('id', '<', $this->logId)
                ->whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->value('id');

            $predecessor = null;
            if ($predecessorId) {
                $predecessor = SystemLog::where('id', $predecessorId)->lockForUpdate()->first();

                if (! $predecessor) {
                    throw new AuditIntegrityException("Predecessor log {$predecessorId} disappeared.");
                }

                // Guard: Check that no unsealed entries exist between predecessor and current entry.
                // Quarantined rows are terminal — they never seal — so they do
                // not block the chain; the entry seals across them with an
                // explicit GAP:<id> previous_hash marker instead.
                // If unsealed entries are found, the immediate predecessor has not been sealed yet,
                // and chaining to a farther predecessor would corrupt the hash chain (fork).
                // Release the transaction and retry with backoff to let the missing seal finish.
                $unsealedBetween = SystemLog::where('id', '>', $predecessorId)
                    ->where('id', '<', $this->logId)
                    ->whereNull('entry_hash')
                    ->where(fn ($q) => $q->whereNull('seal_status')
                        ->orWhere('seal_status', '!=', AuditService::SEAL_STATUS_QUARANTINED))
                    ->exists();

                if ($unsealedBetween) {
                    throw new AuditIntegrityException(
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

            // Get the predecessor's hash (already locked, so stable). A
            // quarantined row between predecessor and target turns the link
            // into an explicit GAP:<id> marker.
            $quarantineBoundary = SystemLog::where('id', '>', $predecessorId ?? 0)
                ->where('id', '<', $this->logId)
                ->where('seal_status', AuditService::SEAL_STATUS_QUARANTINED)
                ->min('id');

            $previousHash = $quarantineBoundary !== null
                ? AuditService::GAP_PREFIX.$quarantineBoundary
                : ($predecessor !== null ? $predecessor->entry_hash : null);

            // Compute this entry's hash. v2 payload: covers old_values,
            // new_values, severity and ip_address so post-seal payload edits
            // no longer verify clean (matches AuditService::sealLogEntry).
            $entryHash = $auditService->computeEntryHash(
                $log->created_at->toIso8601String(),
                $log->user_id,
                $log->action,
                $log->entity_type,
                $log->entity_id,
                $previousHash,
                is_array($log->old_values) ? $log->old_values : null,
                is_array($log->new_values) ? $log->new_values : null,
                $log->severity,
                $log->ip_address
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

        // If the failure was an unsealed-predecessor gap that outlived all
        // retries, the blocking row's own seal is presumed dead. Quarantine
        // it so this entry — and everything behind it — can seal across an
        // explicit gap boundary instead of stalling the chain forever.
        $blockingId = $this->findBlockingEntryId();

        if ($blockingId === null) {
            return;
        }

        SystemLog::where('id', $blockingId)
            ->whereNull('entry_hash')
            ->update(['seal_status' => AuditService::SEAL_STATUS_QUARANTINED]);

        Log::critical('Audit chain gap quarantined after seal retries exhausted', [
            'quarantined_log_id' => $blockingId,
            'blocked_log_id' => $this->logId,
        ]);

        // Re-dispatch so the target seals with a GAP:<id> previous_hash.
        static::dispatch($this->logId);
    }

    /**
     * Earliest unsealed, non-quarantined row sitting between the last sealed
     * predecessor and this job's target — the row that blocked the chain.
     * Null when the failure had no gap to quarantine (missing target row,
     * vanished predecessor, or an unrelated error).
     */
    private function findBlockingEntryId(): ?int
    {
        if (! SystemLog::where('id', $this->logId)->whereNull('entry_hash')->exists()) {
            return null;
        }

        $predecessorId = SystemLog::where('id', '<', $this->logId)
            ->whereNotNull('entry_hash')
            ->orderBy('id', 'desc')
            ->value('id');

        return SystemLog::where('id', '>', $predecessorId ?? 0)
            ->where('id', '<', $this->logId)
            ->whereNull('entry_hash')
            ->where(fn ($q) => $q->whereNull('seal_status')
                ->orWhere('seal_status', '!=', AuditService::SEAL_STATUS_QUARANTINED))
            ->min('id');
    }
}
