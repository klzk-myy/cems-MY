<?php

namespace App\Services;

use App\Enums\AmlRuleType;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Models\AmlRule;
use App\Models\FlaggedTransaction;
use App\Models\SystemLog;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * AML Rule Service
 *
 * Evaluates transactions against configured AML rules and handles
 * the resulting actions (flag, hold, block).
 */
class AmlRuleService
{
    /**
     * Evaluate all active rules against a transaction.
     *
     * @param  Transaction  $transaction  The transaction to evaluate
     * @return array{triggered: bool, rules_triggered: array, total_risk_score: int, action: string}
     */
    public function evaluateTransaction(Transaction $transaction): array
    {
        $applicableRules = $this->getRulesForTransaction($transaction);
        $rulesTriggered = [];
        $totalRiskScore = 0;
        $highestAction = 'none';

        $actionPriority = ['block' => 3, 'hold' => 2, 'flag' => 1, 'none' => 0];

        foreach ($applicableRules as $rule) {
            $result = $rule->evaluate($transaction);

            if ($result['triggered']) {
                $rulesTriggered[] = [
                    'rule' => $rule,
                    'result' => $result,
                ];

                $totalRiskScore += $result['risk_score'];

                $this->logRuleHit($rule, $transaction, $result);

                // Track highest priority action
                if (($actionPriority[$result['action']] ?? 0) > ($actionPriority[$highestAction] ?? 0)) {
                    $highestAction = $result['action'];
                }
            }
        }

        // Cap risk score at 100
        $totalRiskScore = min($totalRiskScore, 100);

        return [
            'triggered' => count($rulesTriggered) > 0,
            'rules_triggered' => $rulesTriggered,
            'total_risk_score' => $totalRiskScore,
            'action' => $highestAction,
        ];
    }

    /**
     * Get rules that are applicable to a transaction.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return Collection<AmlRule>
     */
    public function getRulesForTransaction(Transaction $transaction): Collection
    {
        return AmlRule::active()
            ->get()
            ->filter(fn (AmlRule $rule) => $rule->isApplicableTo($transaction));
    }

    /**
     * Log when a rule is triggered.
     *
     * @param  AmlRule  $rule  The rule that was triggered
     * @param  Transaction  $transaction  The transaction that triggered the rule
     * @param  array  $result  The evaluation result
     */
    public function logRuleHit(AmlRule $rule, Transaction $transaction, array $result): void
    {
        SystemLog::create([
            'user_id' => auth()->id() ?? $transaction->user_id,
            'action' => 'aml_rule_triggered',
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
            'description' => "AML Rule {$rule->rule_code} triggered: {$rule->rule_name}",
            'new_values' => [
                'rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'rule_type' => is_object($rule->rule_type) ? $rule->rule_type->value : $rule->rule_type,
                'action' => $result['action'],
                'risk_score' => $result['risk_score'],
            ],
        ]);
    }

    /**
     * Get breakdown of risk score contributions for a transaction.
     *
     * @param  Transaction  $transaction  The transaction to analyze
     * @return array{applicable_rules: array, risk_contributions: array, total_score: int}
     */
    public function getRiskScoreContributions(Transaction $transaction): array
    {
        $applicableRules = $this->getRulesForTransaction($transaction);
        $riskContributions = [];
        $totalScore = 0;

        foreach ($applicableRules as $rule) {
            $result = $rule->evaluate($transaction);

            $contribution = [
                'rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'rule_name' => $rule->rule_name,
                'rule_type' => is_object($rule->rule_type) ? $rule->rule_type->value : $rule->rule_type,
                'triggered' => $result['triggered'],
                'risk_score' => $result['risk_score'],
                'action' => $result['action'],
            ];

            $riskContributions[] = $contribution;

            if ($result['triggered']) {
                $totalScore += $result['risk_score'];
            }
        }

        return [
            'applicable_rules' => $applicableRules->count(),
            'risk_contributions' => $riskContributions,
            'total_score' => min($totalScore, 100),
        ];
    }

    /**
     * Process a transaction through AML rules and apply actions.
     *
     * @param  Transaction  $transaction  The transaction to process
     * @return array{action_taken: string, rules_triggered: int, transaction_status: TransactionStatus}
     */
    public function processTransaction(Transaction $transaction): array
    {
        $evaluation = $this->evaluateTransaction($transaction);

        if (! $evaluation['triggered']) {
            return [
                'action_taken' => 'none',
                'rules_triggered' => 0,
                'transaction_status' => $transaction->status,
            ];
        }

        $action = $evaluation['action'];
        $holdReasons = [];

        foreach ($evaluation['rules_triggered'] as $triggered) {
            $rule = $triggered['rule'];
            $result = $triggered['result'];

            if ($result['action'] === 'flag') {
                $this->createFlaggedTransaction($transaction, $rule, $result['reason']);
            }

            if (in_array($result['action'], ['flag', 'hold'])) {
                $holdReasons[] = $result['reason'];
            }
        }

        // Apply action to transaction
        $newStatus = $transaction->status;
        $holdReason = $transaction->hold_reason;

        if ($action === 'block') {
            // Blocked transactions are cancelled
            $transaction->update([
                'status' => TransactionStatus::Cancelled,
                'hold_reason' => 'BLOCKED: '.implode('; ', $holdReasons),
            ]);
            $newStatus = TransactionStatus::Cancelled;

            SystemLog::create([
                'user_id' => auth()->id() ?? $transaction->user_id,
                'action' => 'transaction_blocked',
                'entity_type' => 'Transaction',
                'entity_id' => $transaction->id,
                'description' => 'Transaction blocked by AML rule',
                'new_values' => [
                    'rules_triggered' => count($evaluation['rules_triggered']),
                    'total_risk_score' => $evaluation['total_risk_score'],
                ],
            ]);
        } elseif ($action === 'hold' && ! in_array($transaction->status, [TransactionStatus::PendingApproval, TransactionStatus::Completed])) {
            // Only hold if not already pending approval or completed
            $transaction->update([
                'status' => TransactionStatus::PendingApproval,
                'hold_reason' => implode('; ', $holdReasons),
            ]);
            $newStatus = TransactionStatus::PendingApproval;
        }

        return [
            'action_taken' => $action,
            'rules_triggered' => count($evaluation['rules_triggered']),
            'transaction_status' => $newStatus,
            'total_risk_score' => $evaluation['total_risk_score'],
        ];
    }

    /**
     * Create a flagged transaction record.
     *
     * @param  Transaction  $transaction  The transaction to flag
     * @param  AmlRule  $rule  The rule that triggered
     * @param  string|null  $reason  The reason for flagging
     */
    protected function createFlaggedTransaction(Transaction $transaction, AmlRule $rule, ?string $reason): FlaggedTransaction
    {
        return FlaggedTransaction::create([
            'transaction_id' => $transaction->id,
            'flag_type' => ComplianceFlagType::AmlRuleTriggered,
            'flag_reason' => $reason ?? "AML Rule {$rule->rule_code} triggered",
            'status' => FlagStatus::Open,
        ]);
    }

    /**
     * Get rules with their hit counts for reporting.
     *
     * @param  int|null  $days  Number of days to look back
     */
    public function getRulesWithHitCounts(?int $days = 30): Collection
    {
        $startDate = now()->subDays($days);

        return AmlRule::withCount([
            'creator',
        ])
            ->active()
            ->get()
            ->map(function ($rule) use ($startDate) {
                // rule_code is from the AmlRule model (not user input), so safe to interpolate
                $pattern = '%"rule_code":"'.$rule->rule_code.'"%';
                $hitCount = SystemLog::where('action', 'aml_rule_triggered')
                    ->where('new_values', 'LIKE', $pattern)
                    ->where('created_at', '>=', $startDate)
                    ->count();

                return [
                    'rule' => $rule,
                    'hit_count' => $hitCount,
                ];
            });
    }

    /**
     * Validate conditions JSON for a rule type.
     *
     * @param  AmlRuleType  $ruleType  The rule type
     * @param  array  $conditions  The conditions to validate
     * @return array{valid: bool, errors: array}
     */
    public function validateConditions(AmlRuleType $ruleType, array $conditions): array
    {
        $errors = [];

        switch ($ruleType) {
            case AmlRuleType::Velocity:
                if (! isset($conditions['window_hours']) || ! is_numeric($conditions['window_hours'])) {
                    $errors[] = 'window_hours is required and must be numeric';
                }
                if (! isset($conditions['max_transactions']) || ! is_numeric($conditions['max_transactions'])) {
                    $errors[] = 'max_transactions is required and must be numeric';
                }
                break;

            case AmlRuleType::Structuring:
                if (! isset($conditions['window_days']) || ! is_numeric($conditions['window_days'])) {
                    $errors[] = 'window_days is required and must be numeric';
                }
                if (! isset($conditions['min_transaction_count']) || ! is_numeric($conditions['min_transaction_count'])) {
                    $errors[] = 'min_transaction_count is required and must be numeric';
                }
                if (! isset($conditions['aggregate_threshold']) || ! is_numeric($conditions['aggregate_threshold'])) {
                    $errors[] = 'aggregate_threshold is required and must be numeric';
                }
                break;

            case AmlRuleType::AmountThreshold:
                if (! isset($conditions['min_amount']) || ! is_numeric($conditions['min_amount'])) {
                    $errors[] = 'min_amount is required and must be numeric';
                }
                break;

            case AmlRuleType::Frequency:
                if (! isset($conditions['window_hours']) || ! is_numeric($conditions['window_hours'])) {
                    $errors[] = 'window_hours is required and must be numeric';
                }
                if (! isset($conditions['max_transactions']) || ! is_numeric($conditions['max_transactions'])) {
                    $errors[] = 'max_transactions is required and must be numeric';
                }
                break;

            case AmlRuleType::Geographic:
                if (! isset($conditions['countries']) || ! is_array($conditions['countries'])) {
                    $errors[] = 'countries is required and must be an array';
                }
                if (empty($conditions['countries'])) {
                    $errors[] = 'countries array cannot be empty';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
