<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Models\FlaggedTransaction;
use App\Models\StrReport;
use Carbon\Carbon;

/**
 * Monitor for STR filing deadline compliance.
 * Finds flags that should have generated STRs but haven't within the 3 working day deadline.
 */
class StrDeadlineMonitor extends BaseMonitor
{
    public const STR_DEADLINE_DAYS = 3;

    public const WARNING_DAYS_BEFORE = 1;

    protected function getFindingType(): FindingType
    {
        return FindingType::StrDeadline;
    }

    public function run(): array
    {
        $findings = [];

        try {
            $flags = FlaggedTransaction::whereIn('status', ['Open', 'Under_Review'])
                ->whereIn('flag_type', ['Structuring', 'SanctionMatch', 'Velocity', 'HighRiskCustomer'])
                ->get();

            foreach ($flags as $flag) {
                $finding = $this->checkFlagDeadline($flag);
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        } catch (\Throwable $e) {
            Log::error('StrDeadlineMonitor run failed', ['exception' => $e->getMessage()]);

            return [];
        }

        return $findings;
    }

    protected function checkFlagDeadline(FlaggedTransaction $flag): ?array
    {
        // Skip if STR already filed for this flag
        $existingStr = StrReport::where('alert_id', $flag->id)->first();
        if ($existingStr) {
            return null;
        }

        $flagCreatedAt = $flag->created_at instanceof Carbon
            ? $flag->created_at
            : Carbon::parse($flag->created_at);

        $deadline = $flagCreatedAt->copy()->addWeekdays(self::STR_DEADLINE_DAYS);
        $now = now();

        // Overdue
        if ($now->isAfter($deadline)) {
            return $this->createFinding(
                type: FindingType::StrDeadline,
                severity: FindingSeverity::Critical,
                subjectType: 'Transaction',
                subjectId: $flag->transaction_id ?? 0,
                details: [
                    'flag_id' => $flag->id,
                    'flag_type' => $flag->flag_type->value ?? 'Unknown',
                    'flag_created_at' => $flagCreatedAt->toDateTimeString(),
                    'deadline' => $deadline->toDateTimeString(),
                    'days_overdue' => (int) $flagCreatedAt->diffInWeekdays($now) - self::STR_DEADLINE_DAYS,
                    'recommendation' => 'STR must be filed immediately',
                ]
            );
        }

        // Warning window (within 1 day of deadline)
        $warningThreshold = $deadline->copy()->subWeekdays(self::WARNING_DAYS_BEFORE);
        if ($now->isAfter($warningThreshold) || $now->eq($warningThreshold)) {
            return $this->createFinding(
                type: FindingType::StrDeadline,
                severity: FindingSeverity::High,
                subjectType: 'Transaction',
                subjectId: $flag->transaction_id ?? 0,
                details: [
                    'flag_id' => $flag->id,
                    'flag_type' => $flag->flag_type->value ?? 'Unknown',
                    'flag_created_at' => $flagCreatedAt->toDateTimeString(),
                    'deadline' => $deadline->toDateTimeString(),
                    'days_remaining' => (int) $now->diffInWeekdays($deadline),
                    'recommendation' => 'STR filing deadline approaching',
                ]
            );
        }

        return null;
    }
}
