<?php

namespace App\Services\Transaction;

use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\InitialStatusResult;

/**
 * Single source of truth for "does a new booking auto-complete?".
 *
 * Canonical policy (per CLAUDE.md §5): a transaction starts as
 * PendingApproval when a compliance hold is required, the customer risk
 * rating is High or unknown (fail-closed), or the local amount meets the
 * auto-approve threshold. Medium risk alone does not force approval; PEP
 * exposure is already captured upstream via requiresHold / Enhanced CDD.
 *
 * Used by every creation path — wizard, API/service, and CSV import — so
 * the rule cannot diverge between channels.
 */
class InitialStatusResolver
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
    ) {}

    /**
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @param  bool  $holdRequired  Whether a compliance hold is required.
     * @param  RiskRating|null  $riskRating  Customer risk rating; null fails closed to approval.
     * @param  array<int, string>  $holdReasons  Upstream hold reasons (e.g. requiresHold details).
     */
    public function resolve(
        string $amountLocal,
        bool $holdRequired,
        ?RiskRating $riskRating,
        array $holdReasons = []
    ): InitialStatusResult {
        $reasons = $holdReasons;

        if ($holdRequired && $reasons === []) {
            $reasons[] = 'Compliance hold';
        }

        if ($riskRating === null) {
            $reasons[] = 'Customer risk rating is unknown';
        } elseif ($riskRating === RiskRating::High) {
            $reasons[] = 'Customer risk rating is '.$riskRating->value;
        }

        if ($this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            $reasons[] = 'Transaction amount exceeds auto-approve threshold';
        }

        return new InitialStatusResult(
            status: $reasons === [] ? TransactionStatus::Completed : TransactionStatus::PendingApproval,
            holdReason: $reasons === [] ? null : implode('; ', $reasons),
            reasons: $reasons,
        );
    }
}
