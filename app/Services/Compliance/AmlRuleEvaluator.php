<?php

namespace App\Services\Compliance;

use App\Enums\AmlRuleType;
use App\Enums\TransactionStatus;
use App\Models\AmlRule;
use App\Models\Transaction;
use App\Services\System\MathService;
use Illuminate\Support\Facades\Log;

class AmlRuleEvaluator
{
    public function __construct(
        protected MathService $mathService
    ) {}

    /**
     * Evaluate a rule against a transaction.
     *
     * @return array{triggered: bool, risk_score: int, action: string, reason: string|null}
     */
    public function evaluate(Transaction $transaction, AmlRule $rule): array
    {
        if (! $rule->is_active) {
            return [
                'triggered' => false,
                'risk_score' => 0,
                'action' => 'none',
                'reason' => null,
            ];
        }

        $conditions = $rule->conditions ?? [];
        $triggered = false;
        $reason = null;

        // Convert string to enum for evaluation if needed
        $ruleTypeValue = $rule->rule_type;
        if (is_string($ruleTypeValue)) {
            $ruleTypeValue = AmlRuleType::tryFrom($ruleTypeValue);
        }

        try {
            $triggered = match ($ruleTypeValue) {
                AmlRuleType::Velocity => $this->evaluateVelocity($transaction, $conditions),
                AmlRuleType::Structuring => $this->evaluateStructuring($transaction, $conditions),
                AmlRuleType::AmountThreshold => $this->evaluateAmountThreshold($transaction, $conditions),
                AmlRuleType::Frequency => $this->evaluateFrequency($transaction, $conditions),
                AmlRuleType::Geographic => $this->evaluateGeographic($transaction, $conditions),
                default => false,
            };
        } catch (\Throwable $e) {
            Log::error('AML Rule evaluation error', [
                'rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'error' => $e->getMessage(),
            ]);
            $triggered = false;
        }

        if ($triggered) {
            $reason = "Rule {$rule->rule_code}: {$rule->rule_name}";
        }

        return [
            'triggered' => $triggered,
            'risk_score' => $triggered ? $rule->risk_score : 0,
            'action' => $triggered ? $rule->action : 'none',
            'reason' => $reason,
        ];
    }

    /**
     * Evaluate velocity rule.
     * Triggers when customer has too many transactions in a time window.
     */
    protected function evaluateVelocity(Transaction $transaction, array $conditions): bool
    {
        $windowHours = $conditions['window_hours'] ?? 24;
        $maxTransactions = $conditions['max_transactions'] ?? 10;
        $cumulativeThreshold = $conditions['cumulative_threshold'] ?? null;

        $windowStart = now()->subHours($windowHours);

        $query = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', $windowStart)
            ->where('id', '!=', $transaction->id);

        $transactionCount = $query->count();

        if ($transactionCount >= $maxTransactions) {
            return true;
        }

        if ($cumulativeThreshold !== null) {
            $cumulativeAmount = $query->sum('amount_local');
            if ($this->mathService->compare((string) $cumulativeAmount, (string) $cumulativeThreshold) >= 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate structuring rule.
     * Detects multiple transactions that appear to be breaking up a large amount.
     */
    protected function evaluateStructuring(Transaction $transaction, array $conditions): bool
    {
        $windowDays = $conditions['window_days'] ?? 1;
        $minTransactionCount = $conditions['min_transaction_count'] ?? 3;
        $aggregateThreshold = $conditions['aggregate_threshold'] ?? config('thresholds.aml.aggregate_threshold');

        $windowStart = now()->subDays($windowDays);

        $recentTransactions = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', $windowStart)
            ->where('id', '!=', $transaction->id)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        $count = $recentTransactions->count() + 1; // Include current

        if ($count < $minTransactionCount) {
            return false;
        }

        $recentSum = (string) ($recentTransactions->sum('amount_local') ?? '0');
        $totalAmount = $this->mathService->add($recentSum, (string) $transaction->amount_local);

        return $this->mathService->compare($totalAmount, (string) $aggregateThreshold) >= 0;
    }

    /**
     * Evaluate amount threshold rule.
     * Triggers when a single transaction exceeds an amount threshold.
     */
    protected function evaluateAmountThreshold(Transaction $transaction, array $conditions): bool
    {
        $minAmount = $conditions['min_amount'] ?? config('thresholds.aml.amount_threshold', '50000');
        $currency = $conditions['currency'] ?? 'MYR';

        // Only apply to the specified currency (default MYR)
        if ($currency !== $transaction->currency_code) {
            return false;
        }

        return $this->mathService->compare((string) $transaction->amount_local, (string) $minAmount) >= 0;
    }

    /**
     * Evaluate frequency rule.
     * Triggers when customer has too many transactions in a short time window.
     */
    protected function evaluateFrequency(Transaction $transaction, array $conditions): bool
    {
        $windowHours = $conditions['window_hours'] ?? 1;
        $maxTransactions = $conditions['max_transactions'] ?? 10;

        $windowStart = now()->subHours($windowHours);

        $transactionCount = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', $windowStart)
            ->where('id', '!=', $transaction->id)
            ->count();

        return $transactionCount >= $maxTransactions;
    }

    /**
     * Evaluate geographic rule.
     * Triggers when customer nationality or transaction involves high-risk country.
     */
    protected function evaluateGeographic(Transaction $transaction, array $conditions): bool
    {
        $countries = $conditions['countries'] ?? [];
        $matchField = $conditions['match_field'] ?? 'customer_nationality';

        if (empty($countries)) {
            return false;
        }

        $customer = $transaction->customer;
        if (! $customer) {
            return false;
        }

        $valueToCheck = match ($matchField) {
            'customer_nationality' => $customer->nationality,
            default => null,
        };

        if ($valueToCheck === null) {
            return false;
        }

        return in_array(strtoupper($valueToCheck), array_map('strtoupper', $countries), true);
    }
}
