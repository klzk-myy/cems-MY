<?php

namespace App\Enums;

use App\Helpers\Thresholdable;

/**
 * AML Rule Type Enum
 *
 * Represents the different types of AML rules for detecting suspicious activities.
 */
enum AmlRuleType: string
{
    use Thresholdable;
    case Velocity = 'velocity';
    case Structuring = 'structuring';
    case AmountThreshold = 'amount_threshold';
    case Frequency = 'frequency';
    case Geographic = 'geographic';

    /**
     * Get a human-readable label for the rule type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Velocity => 'Velocity',
            self::Structuring => 'Structuring',
            self::AmountThreshold => 'Amount Threshold',
            self::Frequency => 'Frequency',
            self::Geographic => 'Geographic Risk',
        };
    }

    /**
     * Get a description of what this rule type detects.
     */
    public function description(): string
    {
        return match ($this) {
            self::Velocity => 'Detects excessive transaction volume within a time window',
            self::Structuring => 'Detects multiple transactions that may be breaking up a large amount',
            self::AmountThreshold => 'Triggers on single transactions exceeding an amount threshold',
            self::Frequency => 'Detects unusually high transaction frequency',
            self::Geographic => 'Detects transactions involving high-risk countries',
        };
    }

    /**
     * Get the default conditions schema for this rule type.
     */
    public function defaultConditions(): array
    {
        return match ($this) {
            self::Velocity => [
                'window_hours' => 24,
                'max_transactions' => 10,
                'cumulative_threshold' => null,
            ],
            self::Structuring => [
                'window_days' => 1,
                'min_transaction_count' => 3,
                'aggregate_threshold' => self::getAmlAggregateThreshold(),
            ],
            self::AmountThreshold => [
                'min_amount' => self::getLargeTransactionThreshold(),
                'currency' => 'MYR',
            ],
            self::Frequency => [
                'window_hours' => 1,
                'max_transactions' => 10,
            ],
            self::Geographic => [
                'countries' => [],
                'match_field' => 'customer_nationality',
            ],
        };
    }

    /**
     * Get all values as an array for validation.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
