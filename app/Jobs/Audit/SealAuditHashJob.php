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
        $log = SystemLog::find($this->logId);

        // Skip if log was deleted or already sealed
        if (! $log || $log->entry_hash !== null) {
            return;
        }

        // Get the previous log's sealed hash (no lock needed)
        $previousLog = SystemLog::where('id', '<', $log->id)
            ->whereNotNull('entry_hash')
            ->orderBy('id', 'desc')
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

        // Seal the entry
        $log->update([
            'previous_hash' => $previousHash,
            'entry_hash' => $entryHash,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SealAuditHashJob failed permanently', [
            'log_id' => $this->logId,
            'exception' => $exception->getMessage(),
        ]);
    }
}