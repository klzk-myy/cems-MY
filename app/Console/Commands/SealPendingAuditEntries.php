<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Seal system_logs rows left unsealed when their SealAuditHashJob never ran —
 * e.g. a dispatch on a queue connector without after_commit picked the job up
 * before the writing transaction committed, or a worker died between dispatch
 * and execution. Rows are sealed in id order through the same sealLogEntry
 * path the job uses, so predecessor gaps resolve as earlier rows seal.
 * Idempotent; safe to run any time (scheduled hourly).
 *
 * A row that still refuses to seal after --attempts sweep runs is presumed
 * permanently unsealable and quarantined; later rows then seal across it
 * with an explicit GAP:<id> previous_hash marker instead of stalling.
 */
class SealPendingAuditEntries extends Command
{
    protected $signature = 'audit:seal-pending
        {--older-than=5 : Only seal entries created more than N minutes ago}
        {--attempts=3 : Quarantine a row after it fails this many sweep attempts}';

    protected $description = 'Seal committed audit log entries that never received their entry_hash';

    public function handle(AuditService $auditService): int
    {
        // The age cutoff leaves in-flight SealAuditHashJob retries alone —
        // only entries old enough to have exhausted normal dispatch are swept.
        $cutoff = now()->subMinutes((int) $this->option('older-than'));
        $maxAttempts = max(1, (int) $this->option('attempts'));

        $ids = SystemLog::whereNull('entry_hash')
            ->where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNull('seal_status')
                ->orWhere('seal_status', '!=', AuditService::SEAL_STATUS_QUARANTINED))
            ->orderBy('id')
            ->pluck('id');

        $sealed = 0;
        $deferred = 0;
        $quarantined = 0;

        foreach ($ids as $id) {
            try {
                // sealLogEntry returns false when unsealed predecessors sit
                // between this row and the last sealed one; processing in id
                // order seals those predecessors first. A false return only
                // means "still waiting on a gap" — the row itself is not
                // failing, so it does not count toward quarantine.
                if ($auditService->sealLogEntry((int) $id)) {
                    $sealed++;
                } else {
                    $deferred++;
                }

                continue;
            } catch (\Throwable $e) {
                $this->warn("log {$id}: {$e->getMessage()}");
            }

            // The exception came from the row's own seal attempt — that is
            // the failure mode that can stall forever. Count it; after
            // --attempts sweeps the row is presumed permanently unsealable
            // and quarantined so the chain resumes past it.
            $attempts = (int) SystemLog::whereKey($id)->value('seal_attempts') + 1;
            SystemLog::whereKey($id)->update(['seal_attempts' => $attempts]);

            if ($attempts >= $maxAttempts) {
                $auditService->quarantineEntry((int) $id);
                $quarantined++;
                Log::critical('audit:seal-pending quarantined permanently unsealable entry', [
                    'log_id' => $id,
                    'attempts' => $attempts,
                ]);
            } else {
                $deferred++;
            }
        }

        $this->info("audit:seal-pending — {$sealed} sealed, {$deferred} deferred, {$quarantined} quarantined of {$ids->count()} pending.");

        return self::SUCCESS;
    }
}
