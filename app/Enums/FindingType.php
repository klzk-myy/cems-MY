<?php

namespace App\Enums;

/**
 * Finding Type Enum
 *
 * Represents the different types of compliance findings that can be generated
 * by the AML monitoring system.
 */
enum FindingType: string
{
    case VelocityExceeded = 'Velocity_Exceeded';
    case StructuringPattern = 'Structuring_Pattern';
    case AggregateTransaction = 'Aggregate_Transaction';
    case StrDeadline = 'STR_Deadline';
    case SanctionMatch = 'Sanction_Match';
    case LocationAnomaly = 'Location_Anomaly';
    case CurrencyFlowAnomaly = 'Currency_Flow_Anomaly';
    case CounterfeitAlert = 'Counterfeit_Alert';
    case RiskScoreChange = 'Risk_Score_Change';

    /**
     * Get a human-readable label for the finding type.
     */
    public function label(): string
    {
        return match ($this) {
            self::VelocityExceeded => 'Velocity Exceeded',
            self::StructuringPattern => 'Structuring Pattern',
            self::AggregateTransaction => 'Aggregate Transaction',
            self::StrDeadline => 'STR Deadline',
            self::SanctionMatch => 'Sanction Match',
            self::LocationAnomaly => 'Location Anomaly',
            self::CurrencyFlowAnomaly => 'Currency Flow Anomaly',
            self::CounterfeitAlert => 'Counterfeit Alert',
            self::RiskScoreChange => 'Risk Score Change',
        };
    }

    /**
     * Get the default severity level for this finding type.
     */
    public function defaultSeverity(): FindingSeverity
    {
        return match ($this) {
            self::SanctionMatch, self::CounterfeitAlert => FindingSeverity::Critical,
            self::VelocityExceeded, self::StructuringPattern => FindingSeverity::High,
            self::AggregateTransaction, self::StrDeadline => FindingSeverity::Medium,
            self::LocationAnomaly, self::CurrencyFlowAnomaly, self::RiskScoreChange => FindingSeverity::Low,
        };
    }
}
