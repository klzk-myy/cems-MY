<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\AuditService;
use App\Services\System\SystemAlertService;
use Illuminate\Console\Command;

/**
 * Raise a persistent SystemAlert when audit entries stay unsealed beyond a
 * count or age threshold — the seal jobs and the hourly sweeper have both
 * failed to drain them, so a human needs to look before the gap ages into
 * a quarantine.
 *
 * Dedup: an unacknowledged alert from this source suppresses repeats, so
 * the scheduled run pages once per incident, not once per run.
 * Idempotent; scheduled hourly alongside audit:seal-pending.
 */
class AuditWatchUnsealed extends Command
{
    protected $signature = 'audit:watch-unsealed
        {--count=25 : Alert when more than N entries are unsealed}
        {--age=60 : Alert when the oldest unsealed entry is more than N minutes old}';

    protected $description = 'Alert on aged or excessive unsealed audit entries';

    private const SOURCE = 'audit_chain';

    public function handle(AuditService $auditService, SystemAlertService $alerts): int
    {
        $unsealed = $auditService->getUnsealedCount();
        $oldestAt = $auditService->getOldestUnsealedAt();
        $ageMinutes = $oldestAt?->diffInMinutes(now());

        $countExceeded = $unsealed > (int) $this->option('count');
        $ageExceeded = $oldestAt !== null && $ageMinutes > (int) $this->option('age');

        if (! $countExceeded && ! $ageExceeded) {
            $this->info("audit:watch-unsealed — {$unsealed} unsealed, within thresholds.");

            return self::SUCCESS;
        }

        $alreadyOpen = SystemAlert::unacknowledged()
            ->where('source', self::SOURCE)
            ->exists();

        if ($alreadyOpen) {
            $this->info('audit:watch-unsealed — thresholds exceeded but an unacknowledged alert is already open.');

            return self::SUCCESS;
        }

        $alerts->critical(
            "Audit seal chain stalled: {$unsealed} entries unsealed"
                .($oldestAt ? ", oldest {$oldestAt->toIso8601String()} ({$ageMinutes}m ago)." : '.'),
            [
                'source' => self::SOURCE,
                'metadata' => [
                    'unsealed_count' => $unsealed,
                    'oldest_unsealed_at' => $oldestAt?->toIso8601String(),
                    'count_threshold' => (int) $this->option('count'),
                    'age_threshold_minutes' => (int) $this->option('age'),
                ],
            ]
        );

        $this->warn("audit:watch-unsealed — critical alert raised ({$unsealed} unsealed).");

        return self::SUCCESS;
    }
}
