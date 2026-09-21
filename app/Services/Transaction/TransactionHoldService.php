<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;

class TransactionHoldService
{
    public const CRITICAL_SEVERITY = 'critical';

    /**
     * Determine if a transaction requires a hold based on CDD level and risk flags.
     * - Enhanced CDD always requires hold
     * - Any critical risk flag requires hold
     *
     * @param  array  $riskFlags  Each flag should have 'severity' key
     */
    public function requiresHold(CddLevel $cddLevel, array $riskFlags = []): bool
    {
        if ($cddLevel === CddLevel::Enhanced) {
            return true;
        }

        foreach ($riskFlags as $flag) {
            if (isset($flag['severity']) && $flag['severity'] === self::CRITICAL_SEVERITY) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get hold reasons for audit logging.
     *
     * @return array<string>
     */
    public function getHoldReasons(CddLevel $cddLevel, array $riskFlags = []): array
    {
        $reasons = [];

        if ($cddLevel === CddLevel::Enhanced) {
            $reasons[] = 'Enhanced CDD requires hold';
        }

        foreach ($riskFlags as $flag) {
            if (isset($flag['severity']) && $flag['severity'] === self::CRITICAL_SEVERITY) {
                $reason = $flag['type'] ?? 'Critical risk flag';
                $reasons[] = "Critical risk: {$reason}";
            }
        }

        return $reasons;
    }
}
