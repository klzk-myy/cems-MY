<?php

namespace App\Models;

use App\Enums\AmlRuleType;
use App\Services\Compliance\AmlRuleEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
class AmlRule extends BaseModel
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
        'rule_type' => AmlRuleType::class,
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
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter rules by type.
     *
     * @param  AmlRuleType|string  $type
     */
    public function scopeByType(Builder $query, $type): Builder
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
        return app(AmlRuleEvaluator::class)->evaluate($transaction, $this);
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
