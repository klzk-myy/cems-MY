<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditChainService;
use Illuminate\Console\Command;

class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify {--limit= : Number of most-recent entries to verify (default: all)}';

    protected $description = 'Verify the tamper-evident audit hash chain integrity';

    public function handle(AuditChainService $auditChainService): int
    {
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== '' ? max(1, (int) $limitOption) : null;

        $result = $auditChainService->verifyChainIntegrity($limit);

        if ((bool) ($result['valid'] ?? false)) {
            $this->info($result['message'] ?? 'Audit chain OK.');

            return 0;
        }

        $brokenAt = $result['broken_at'] ?? null;

        $this->error('AUDIT CHAIN INTEGRITY FAILURE'
            .($brokenAt ? " at log entry #{$brokenAt}" : '')
            .': '.($result['message'] ?? 'unknown error'));

        return 1;
    }
}
