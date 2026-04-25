<?php

namespace App\Models;

use App\Enums\AmlRuleType;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * AML Rule Model
 *
 * Represents an Anti-Money Laundering rule for detecting suspicious transactions.
 * Rules can be velocity-based, structuring detection, amount threshold, frequency,
 * or geographic-based.
 *
 * @property int $id
 * @property string $rule_code Unique rule identifier
 * @property string $rule_name Human-readable rule name
 * @property string|null $description Rule description
 * @property string|null $rule_type Type of rule (velocity, structuring, amount_threshold, frequency, geographic)
 * @property array|null $conditions JSON conditions for rule evaluation
 * @property string $action Action when rule triggers (flag, hold, block)
 * @property int $risk_score Risk score contribution (0-100)
 * @property bool $is_active Whether rule is active
 * @property int|null $created_by User who created the rule
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AmlRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'rule_code',
        'rule_name',
        'description',
        'rule_type',
        'conditions',
        'action',
        'risk_score',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conditions' => 'array',
        'risk_score' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active rules.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter rules by type.
     *
     * @param  Builder  $query
     * @param  AmlRuleType|string  $type
     * @return Builder
     */
    public function scopeByType($query, $type)
    {
        if ($type instanceof AmlRuleType) {
            $type = $type->value;
        }

        return $query->where('rule_type', $type);
    }

    /**
     * Evaluate this rule against a transaction.
     *
     * @param  Transaction  $transaction  The transaction to evaluate
     * @return array{triggered: bool, risk_score: int, action: string, reason: string|null}
     */
    public function evaluate(Transaction $transaction): array
    {
        if (! $this->is_active) {
            return [
                'triggered' => false,
                'risk_score' => 0,
                'action' => 'none',
                'reason' => null,
            ];
        }

        $conditions = $this->conditions ?? [];
        $triggered = false;
        $reason = null;

        // Convert string to enum for evaluation if needed
        $ruleTypeValue = $this->rule_type;
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
        } catch (\Exception $e) {
            Log::error('AML Rule evaluation error', [
                'rule_id' => $this->id,
                'rule_code' => $this->rule_code,
                'error' => $e->getMessage(),
            ]);
            $triggered = false;
        }

        if ($triggered) {
            $reason = "Rule {$this->rule_code}: {$this->rule_name}";
        }

        return [
            'triggered' => $triggered,
            'risk_score' => $triggered ? $this->risk_score : 0,
            'action' => $triggered ? $this->action : 'none',
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

        // Check cumulative threshold if specified
        if ($cumulativeThreshold !== null) {
            $cumulativeAmount = $query->sum('amount_local');
            if (bccomp((string) $cumulativeAmount, (string) $cumulativeThreshold, 4) >= 0) {
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
        $totalAmount = bcadd($recentSum, (string) $transaction->amount_local, 4);

        return bccomp($totalAmount, (string) $aggregateThreshold, 4) >= 0;
    }

    /**
     * Evaluate amount threshold rule.
     * Triggers when a single transaction exceeds an amount threshold.
     */
    protected function evaluateAmountThreshold(Transaction $transaction, array $conditions): bool
    {
        $minAmount = $conditions['min_amount'] ?? config('thresholds.aml.amount_threshold', '50000');
        $currency = $conditions['currency'] ?? 'MYR';

        // Check if transaction currency matches (or defaults to MYR)
        if ($currency !== 'MYR' && $transaction->currency_code !== $currency) {
            return false;
        }

        return bccomp((string) $transaction->amount_local, (string) $minAmount, 4) >= 0;
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

    /**
     * Check if this rule matches a transaction type/context.
     * Used to filter rules before evaluation.
     */
    public function isApplicableTo(Transaction $transaction): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $conditions = $this->conditions ?? [];

        // Get rule type value (handle both string and enum)
        $ruleTypeValue = is_object($this->rule_type) ? $this->rule_type->value : $this->rule_type;

        // For geographic rules, check if customer's nationality matches
        if ($ruleTypeValue === AmlRuleType::Geographic->value) {
            $customer = $transaction->customer;
            if (! $customer || ! $customer->nationality) {
                return false;
            }

            // Check if nationality matches the rule's countries
            $countries = $conditions['countries'] ?? [];
            if (! empty($countries) && ! in_array($customer->nationality, $countries)) {
                return false;
            }
        }

        return true;
    }
}
