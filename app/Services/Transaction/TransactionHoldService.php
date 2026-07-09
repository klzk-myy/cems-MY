<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Services\Contracts\TransactionHoldServiceInterface;

class TransactionHoldService implements TransactionHoldServiceInterface
{
    /**
     * Determine if a transaction requires a hold based on CDD level and risk flags.
     * Exact logic from TransactionService::determineHoldRequired():
     * - Enhanced CDD always requires hold
     * - Any critical risk flag requires hold
     *
     * @param  Customer  $customer  (kept for interface compatibility; not used in current logic)
     * @param  array  $riskFlags  Each flag should have 'severity' key
     */
    public function requiresHold(CddLevel $cddLevel, Customer $customer, array $riskFlags = []): bool
    {
        if ($cddLevel === CddLevel::Enhanced) {
            return true;
        }

        foreach ($riskFlags as $flag) {
            if (isset($flag['severity']) && $flag['severity'] === 'critical') {
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
    public function getHoldReasons(CddLevel $cddLevel, Customer $customer, array $riskFlags = []): array
    {
        $reasons = [];

        if ($cddLevel === CddLevel::Enhanced) {
            $reasons[] = 'Enhanced CDD requires hold';
        }

        foreach ($riskFlags as $flag) {
            if (isset($flag['severity']) && $flag['severity'] === 'critical') {
                $reason = $flag['type'] ?? 'Critical risk flag';
                $reasons[] = "Critical risk: {$reason}";
            }
        }

        return $reasons;
    }
}
