<?php

namespace App\Services\Compliance;

use App\Enums\AmlRuleType;
use App\Enums\TransactionStatus;
use App\Models\AmlRule;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\Log;

class AmlRuleEvaluator
{
    public function __construct(
        protected MathService $mathService,
        protected ?ThresholdService $thresholdService = null,
    ) {
        $this->thresholdService ??= app(ThresholdService::class);
    }

    /**
     * Evaluate a rule against a transaction.
     *
     * @return array{triggered: bool, risk_score: int, action: string, reason: string|null}
     */
    public function evaluate(Transaction $transaction, AmlRule $rule, ?Customer $customer = null): array
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

        // Resolve the stored rule type defensively. getRawOriginal() reflects
        // the DB-load snapshot, but newly-built (unsaved) rules have no original
        // yet, so fall back to the live attributes. Legacy seeded rules store
        // types outside the AmlRuleType enum (e.g. 'threshold'), so going
        // through the cast would throw a ValueError before tryFrom runs.
        $ruleTypeValue = $rule->getRawOriginal('rule_type') ?? ($rule->getAttributes()['rule_type'] ?? null);

        if ($ruleTypeValue instanceof AmlRuleType) {
            $ruleTypeValue = $ruleTypeValue->value;
        }

        if (is_string($ruleTypeValue)) {
            $ruleTypeValue = AmlRuleType::tryFrom($ruleTypeValue);
        }

        try {
            $triggered = match ($ruleTypeValue) {
                AmlRuleType::Velocity => $this->evaluateVelocity($transaction, $conditions),
                AmlRuleType::Structuring => $this->evaluateStructuring($transaction, $conditions),
                AmlRuleType::AmountThreshold => $this->evaluateAmountThreshold($transaction, $conditions),
                AmlRuleType::Frequency => $this->evaluateFrequency($transaction, $conditions),
                AmlRuleType::Geographic => $this->evaluateGeographic($transaction, $conditions, $customer),
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
     * Pipeline hook: evaluate every ACTIVE rule against a transaction.
     *
     * Cheap by design: a single indexed query loads active rules and the
     * method returns immediately when none exist, so it can be invoked
     * unconditionally after booking. Callers MUST wrap this in try/catch so
     * an evaluator failure never blocks booking. Triggered rules are logged
     * through the evaluator's existing Log channel so findings are visible
     * even if the caller drops the returned result.
     *
     * @return array<int, array{triggered: true, risk_score: int, action: string, reason: string, rule_id: int, rule_code: string, rule_name: string}>
     */
    public function evaluateActiveRules(Transaction $transaction, ?Customer $customer = null): array
    {
        // Single indexed query on is_active; skip all further work when the
        // rule table is empty or nothing is enabled.
        $rules = AmlRule::query()->where('is_active', true)->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $customer ??= $transaction->customer;
        $findings = [];

        foreach ($rules as $rule) {
            $result = $this->evaluate($transaction, $rule, $customer);

            if (! $result['triggered']) {
                continue;
            }

            $result['rule_id'] = $rule->id;
            $result['rule_code'] = $rule->rule_code;
            $result['rule_name'] = $rule->rule_name;
            $findings[] = $result;

            Log::warning('AML rule triggered', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'action' => $result['action'],
                'risk_score' => $result['risk_score'],
                'reason' => $result['reason'],
            ]);
        }

        return $findings;
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
        $aggregateThreshold = $conditions['aggregate_threshold'] ?? $this->thresholdService->getAmlAggregateThreshold();

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

        // BCMath accumulation: Collection::sum() casts DECIMAL strings to
        // float, which can flip the comparison near the aggregate threshold.
        $recentSum = '0';
        foreach ($recentTransactions as $recentTransaction) {
            $recentSum = $this->mathService->add($recentSum, (string) $recentTransaction->amount_local);
        }

        $totalAmount = $this->mathService->add($recentSum, (string) $transaction->amount_local);

        return $this->mathService->compare($totalAmount, (string) $aggregateThreshold) >= 0;
    }

    /**
     * Evaluate amount threshold rule.
     * Triggers when a single transaction exceeds an amount threshold.
     */
    protected function evaluateAmountThreshold(Transaction $transaction, array $conditions): bool
    {
        $minAmount = $conditions['min_amount'] ?? $this->thresholdService->getAmlAmountThreshold();
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
    protected function evaluateGeographic(Transaction $transaction, array $conditions, ?Customer $customer = null): bool
    {
        $countries = $conditions['countries'] ?? [];
        $matchField = $conditions['match_field'] ?? 'customer_nationality';

        if (empty($countries)) {
            return false;
        }

        $customer = $customer ?? $transaction->customer;
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
