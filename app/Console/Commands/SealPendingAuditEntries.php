<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Console\Command;

/**
 * Seal system_logs rows left unsealed when their SealAuditHashJob never ran —
 * e.g. a dispatch on a queue connector without after_commit picked the job up
 * before the writing transaction committed, or a worker died between dispatch
 * and execution. Rows are sealed in id order through the same sealLogEntry
 * path the job uses, so predecessor gaps resolve as earlier rows seal.
 * Idempotent; safe to run any time (scheduled hourly).
 */
class SealPendingAuditEntries extends Command
{
    protected $signature = 'audit:seal-pending {--older-than=5 : Only seal entries created more than N minutes ago}';

    protected $description = 'Seal committed audit log entries that never received their entry_hash';

    public function handle(AuditService $auditService): int
    {
        // The age cutoff leaves in-flight SealAuditHashJob retries alone —
        // only entries old enough to have exhausted normal dispatch are swept.
        $cutoff = now()->subMinutes((int) $this->option('older-than'));

        $ids = SystemLog::whereNull('entry_hash')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $sealed = 0;
        $deferred = 0;

        foreach ($ids as $id) {
            try {
                // sealLogEntry returns false when unsealed predecessors sit
                // between this row and the last sealed one; processing in id
                // order seals those predecessors first, so a false here means
                // a genuinely unsealable gap remains.
                $auditService->sealLogEntry((int) $id) ? $sealed++ : $deferred++;
            } catch (\Throwable $e) {
                $deferred++;
                $this->warn("log {$id}: {$e->getMessage()}");
            }
        }

        $this->info("audit:seal-pending — {$sealed} sealed, {$deferred} deferred of {$ids->count()} pending.");

        return self::SUCCESS;
    }
}
