<?php

namespace App\Support;

/**
 * Presentation + validation metadata for every threshold in
 * config/thresholds.php, grouped by functional domain. Shared by the admin
 * thresholds page and thresholds:show so labels/units live in one place.
 *
 * Each key entry: label, unit, description.
 * Each category may declare `ordered` chains: key lists whose effective
 * values must be non-decreasing in the given order (e.g. low <= medium <=
 * high). Chains are enforced by UpdateThresholdsRequest.
 *
 * @phpstan-type KeyMeta array{label: string, unit: string, description: string}
 * @phpstan-type CategoryMeta array{label: string, description: string, keys: array<string, KeyMeta>, ordered?: list<list<string>>, dynamic_keys?: bool}
 */
final class ThresholdMetadata
{
    /**
     * @return array<string, CategoryMeta>
     */
    public static function categories(): array
    {
        return [
            'approval' => [
                'label' => 'Approval',
                'description' => 'Transaction amounts that decide whether a transaction auto-completes or needs manager/compliance approval.',
                'keys' => [
                    'auto_approve' => ['label' => 'Auto-approve below', 'unit' => 'MYR', 'description' => 'Transactions below this amount auto-complete when the customer is not High risk.'],
                    'manager' => ['label' => 'Compliance approval at/above', 'unit' => 'MYR', 'description' => 'Amounts at or above this require compliance-tier approval.'],
                ],
                'ordered' => [['auto_approve', 'manager']],
            ],
            'cdd' => [
                'label' => 'Customer Due Diligence',
                'description' => 'CDD level boundaries per BNM 14C.12 — Simplified, Specific, Standard and Enhanced.',
                'keys' => [
                    'specific' => ['label' => 'Specific CDD at/above', 'unit' => 'MYR', 'description' => 'Transactions at or above this leave the Simplified band.'],
                    'standard' => ['label' => 'Standard CDD at/above', 'unit' => 'MYR', 'description' => 'Transactions at or above this require full Standard CDD documentation.'],
                    'large_transaction' => ['label' => 'Large transaction (EDD trigger)', 'unit' => 'MYR', 'description' => 'Amounts at or above this count toward Enhanced due-diligence triggers.'],
                ],
                'ordered' => [['specific', 'standard', 'large_transaction']],
            ],
            'risk_scoring' => [
                'label' => 'Risk Scoring — Amount Bands',
                'description' => 'MYR amount bands used by the amount/velocity risk components, plus the 0–100 customer score bands used for rescreening.',
                'keys' => [
                    'high' => ['label' => 'High band at/above', 'unit' => 'MYR', 'description' => 'Transaction amount scoring the highest amount-risk band.'],
                    'medium' => ['label' => 'Medium band at/above', 'unit' => 'MYR', 'description' => 'Transaction amount scoring the medium amount-risk band.'],
                    'low' => ['label' => 'Low band at/above', 'unit' => 'MYR', 'description' => 'Transaction amount scoring the low amount-risk band.'],
                    'score_high' => ['label' => 'Score: High at/above', 'unit' => 'points', 'description' => 'Customer risk score (0–100) classified High.'],
                    'score_medium' => ['label' => 'Score: Medium at/above', 'unit' => 'points', 'description' => 'Customer risk score (0–100) classified Medium.'],
                    'score_low' => ['label' => 'Score: Low at/above', 'unit' => 'points', 'description' => 'Customer risk score (0–100) classified Low.'],
                    'rescreening_days' => ['label' => 'Rescreening interval', 'unit' => 'days', 'description' => 'Customers whose risk was last assessed longer ago than this are due for rescreening.'],
                ],
                'ordered' => [['low', 'medium', 'high'], ['score_low', 'score_medium', 'score_high']],
            ],
            'alert_triage' => [
                'label' => 'Alert Triage',
                'description' => 'Transaction amounts that set the initial severity of compliance alerts.',
                'keys' => [
                    'critical' => ['label' => 'Critical at/above', 'unit' => 'MYR', 'description' => 'Alerts involving amounts at or above this start Critical.'],
                    'high' => ['label' => 'High at/above', 'unit' => 'MYR', 'description' => 'Alerts involving amounts at or above this start High.'],
                    'medium' => ['label' => 'Medium at/above', 'unit' => 'MYR', 'description' => 'Alerts involving amounts at or above this start Medium.'],
                ],
                'ordered' => [['medium', 'high', 'critical']],
            ],
            'alert_sla_hours' => [
                'label' => 'Alert SLAs',
                'description' => 'Working hours allowed to action an alert per severity before it breaches SLA.',
                'keys' => [
                    'critical' => ['label' => 'Critical', 'unit' => 'hours', 'description' => 'Response window for Critical alerts.'],
                    'high' => ['label' => 'High', 'unit' => 'hours', 'description' => 'Response window for High alerts.'],
                    'medium' => ['label' => 'Medium', 'unit' => 'hours', 'description' => 'Response window for Medium alerts.'],
                    'low' => ['label' => 'Low', 'unit' => 'hours', 'description' => 'Response window for Low alerts.'],
                ],
                'ordered' => [['critical', 'high', 'medium', 'low']],
            ],
            'case_sla_hours' => [
                'label' => 'Case SLAs',
                'description' => 'Working hours allowed to resolve a compliance case per severity before it breaches SLA.',
                'keys' => [
                    'critical' => ['label' => 'Critical', 'unit' => 'hours', 'description' => 'Resolution window for Critical cases.'],
                    'high' => ['label' => 'High', 'unit' => 'hours', 'description' => 'Resolution window for High cases.'],
                    'medium' => ['label' => 'Medium', 'unit' => 'hours', 'description' => 'Resolution window for Medium cases.'],
                    'low' => ['label' => 'Low', 'unit' => 'hours', 'description' => 'Resolution window for Low cases.'],
                ],
                'ordered' => [['critical', 'high', 'medium', 'low']],
            ],
            'reporting' => [
                'label' => 'Regulatory Reporting',
                'description' => 'BNM reportable-transaction amounts.',
                'keys' => [
                    'str' => ['label' => 'STR at/above', 'unit' => 'MYR', 'description' => 'Suspicious transaction report amount reference.'],
                    'edd' => ['label' => 'EDD reporting at/above', 'unit' => 'MYR', 'description' => 'Amounts at or above this are included in EDD reporting.'],
                ],
            ],
            'structuring' => [
                'label' => 'Structuring Detection',
                'description' => 'Detection of transactions split to stay under reporting/CDD amounts, and the score tiers applied to hits.',
                'keys' => [
                    'sub_threshold' => ['label' => 'Sub-threshold amount', 'unit' => 'MYR', 'description' => 'Transactions just below this amount are structuring candidates.'],
                    'min_transactions' => ['label' => 'Minimum transactions', 'unit' => 'count', 'description' => 'Minimum count of sub-threshold transactions before flagging.'],
                    'hourly_window' => ['label' => 'Hourly window', 'unit' => 'hours', 'description' => 'Transactions within this window count toward a structuring pattern.'],
                    'lookup_days' => ['label' => 'Lookup days', 'unit' => 'days', 'description' => 'Trailing window scanned for structuring patterns.'],
                    'score_min_count_high' => ['label' => 'High score: min count', 'unit' => 'count', 'description' => 'Transactions-per-hour at or above this earn the high structuring score.'],
                    'score_high' => ['label' => 'High score points', 'unit' => 'points', 'description' => 'Risk points for the high structuring tier.'],
                    'score_min_count_low' => ['label' => 'Low score: min count', 'unit' => 'count', 'description' => 'Transactions-per-hour at or above this earn the low structuring score.'],
                    'score_low' => ['label' => 'Low score points', 'unit' => 'points', 'description' => 'Risk points for the low structuring tier.'],
                    'score_cap' => ['label' => 'Score cap', 'unit' => 'points', 'description' => 'Maximum structuring points applied to a customer.'],
                ],
                'ordered' => [['score_min_count_low', 'score_min_count_high'], ['score_low', 'score_high', 'score_cap']],
            ],
            'monitoring' => [
                'label' => 'Unusual-Pattern Monitoring',
                'description' => 'Deviation-from-baseline detection for unusual customer activity.',
                'keys' => [
                    'unusual_pattern_lookback_days' => ['label' => 'Lookback', 'unit' => 'days', 'description' => 'Trailing window used to compute the customer baseline.'],
                    'unusual_pattern_multiplier' => ['label' => 'Deviation multiplier', 'unit' => '×', 'description' => 'Amounts beyond this multiple of the baseline are flagged.'],
                ],
            ],
            'duration' => [
                'label' => 'Transaction Duration',
                'description' => 'How long a transaction may remain open before warnings escalate.',
                'keys' => [
                    'warning_hours' => ['label' => 'Warning at/above', 'unit' => 'hours', 'description' => 'Open transactions older than this raise a warning.'],
                    'critical_hours' => ['label' => 'Critical at/above', 'unit' => 'hours', 'description' => 'Open transactions older than this escalate to Critical.'],
                ],
                'ordered' => [['warning_hours', 'critical_hours']],
            ],
            'variance' => [
                'label' => 'Counter / Till Variance',
                'description' => 'EOD till-count variances that trigger yellow and red alerts.',
                'keys' => [
                    'yellow' => ['label' => 'Yellow at/above', 'unit' => 'MYR', 'description' => 'Absolute till variance at or above this raises a Yellow alert.'],
                    'red' => ['label' => 'Red at/above', 'unit' => 'MYR', 'description' => 'Absolute till variance at or above this raises a Red alert.'],
                ],
                'ordered' => [['yellow', 'red']],
            ],
            'velocity' => [
                'label' => 'Velocity Monitoring',
                'description' => 'Per-customer transaction velocity amounts and windows.',
                'keys' => [
                    'alert_threshold' => ['label' => 'Alert at/above', 'unit' => 'MYR', 'description' => 'Aggregated velocity at or above this raises an alert.'],
                    'warning_threshold' => ['label' => 'Warning at/above', 'unit' => 'MYR', 'description' => 'Aggregated velocity at or above this raises a warning.'],
                    'window_days' => ['label' => 'Scoring window', 'unit' => 'days', 'description' => 'Trailing window used for velocity scoring.'],
                    'amount_window_hours' => ['label' => 'Amount window', 'unit' => 'hours', 'description' => 'Lookback window for the per-customer amount-threshold check.'],
                ],
                'ordered' => [['warning_threshold', 'alert_threshold']],
            ],
            'aml' => [
                'label' => 'AML Rules',
                'description' => 'Amounts driving the AML rule engine.',
                'keys' => [
                    'amount_threshold' => ['label' => 'Single-transaction amount', 'unit' => 'MYR', 'description' => 'Single transactions at or above this trip the amount rule.'],
                    'aggregate_threshold' => ['label' => 'Aggregate amount', 'unit' => 'MYR', 'description' => 'Aggregated activity at or above this trips the aggregate rule.'],
                ],
            ],
            'currency_flow' => [
                'label' => 'Currency-Flow Monitoring',
                'description' => 'Round-trip and flow-pattern detection between currencies.',
                'keys' => [
                    'round_trip_threshold' => ['label' => 'Round-trip amount', 'unit' => 'MYR', 'description' => 'Round-trip flows at or above this are flagged.'],
                    'lookback_days' => ['label' => 'Lookback', 'unit' => 'days', 'description' => 'Trailing window scanned for flow patterns.'],
                ],
            ],
            'geographic_risk' => [
                'label' => 'Geographic Risk',
                'description' => 'Risk-score point weights for geographic components — not MYR amounts.',
                'keys' => [
                    'high_country_weight' => ['label' => 'High-risk country weight', 'unit' => 'points', 'description' => 'Points added per high-risk nationality hit.'],
                    'recent_travel_weight' => ['label' => 'Recent travel weight', 'unit' => 'points', 'description' => 'Points added per recent-travel hit.'],
                ],
            ],
            'kyc' => [
                'label' => 'KYC Expiry',
                'description' => 'Grace period after document expiry before transactions are blocked.',
                'keys' => [
                    'grace_period_days' => ['label' => 'Grace period', 'unit' => 'days', 'description' => 'Days after KYC document expiry before blocking transactions.'],
                ],
            ],
            'risk_review' => [
                'label' => 'Risk Review',
                'description' => 'Periodic customer risk-score recalculation.',
                'keys' => [
                    'batch_size' => ['label' => 'Batch size', 'unit' => 'count', 'description' => 'Customers processed per risk-review run.'],
                ],
            ],
            'rates' => [
                'label' => 'Exchange-Rate Spreads',
                'description' => 'Buy/sell spread bounds and rate-handling precision. Fractions, not percentages (0.02 = 2%).',
                'keys' => [
                    'spread' => ['label' => 'Spread', 'unit' => 'fraction', 'description' => 'Buy/sell spread around the mid rate (0.02 = 2%).'],
                    'min_spread' => ['label' => 'Minimum spread', 'unit' => 'fraction', 'description' => 'Smallest allowed spread.'],
                    'max_spread' => ['label' => 'Maximum spread', 'unit' => 'fraction', 'description' => 'Largest allowed spread.'],
                    'max_deviation_percent' => ['label' => 'Max rate deviation', 'unit' => 'fraction', 'description' => 'Outer sanity band: teller-submitted rates may deviate from market by at most this fraction (0.05 = 5%). The tighter per-role limits below apply on top of it.'],
                    'precision' => ['label' => 'Rate precision', 'unit' => 'decimals', 'description' => 'Decimal places used for exchange rates and positions.'],
                    'cache_duration' => ['label' => 'Rate cache TTL', 'unit' => 'seconds', 'description' => 'How long fetched rates are cached.'],
                    'override_limit_teller' => ['label' => 'Teller override limit', 'unit' => 'percent', 'description' => 'Maximum rate deviation (percentage points) a teller may book without approval (BNM). Enforced at booking time.'],
                    'override_limit_manager' => ['label' => 'Manager override limit', 'unit' => 'percent', 'description' => 'Maximum rate deviation a manager may apply without approval (BNM).'],
                ],
                'ordered' => [['min_spread', 'spread', 'max_spread']],
            ],
            'performance' => [
                'label' => 'Performance Monitoring',
                'description' => 'Operational warning levels for the performance dashboard.',
                'keys' => [
                    'response_time_warning' => ['label' => 'Response time', 'unit' => 'ms', 'description' => 'Requests slower than this are flagged.'],
                    'cache_hit_rate_warning' => ['label' => 'Cache hit rate', 'unit' => '%', 'description' => 'Cache hit rates below this are flagged.'],
                    'query_time_warning' => ['label' => 'Query time', 'unit' => 'ms', 'description' => 'Queries slower than this are flagged.'],
                    'job_duration_warning' => ['label' => 'Job duration', 'unit' => 'ms', 'description' => 'Queued jobs running longer than this are flagged.'],
                ],
            ],
            'position_limits' => [
                'label' => 'Currency Position Limits',
                'description' => 'Maximum holdings per currency before escalation/reporting. Values are in foreign-currency units; keys are currency codes.',
                'keys' => [],
                'dynamic_keys' => true,
            ],
            'low_stock' => [
                'label' => 'Low Stock Alerts',
                'description' => 'Total per-currency position below this trips a low-stock alert.',
                'keys' => [
                    'threshold' => ['label' => 'Low stock threshold', 'unit' => 'units', 'description' => 'Foreign-currency position below this raises a low-stock alert (LowStockAlertJob).'],
                ],
            ],
        ];
    }

    /**
     * Metadata for one key, with a fallback for dynamic categories such as
     * position_limits whose key set comes from config/DB rather than this map.
     *
     * @return KeyMeta
     */
    public static function key(string $category, string $key): array
    {
        $meta = self::categories()[$category]['keys'][$key] ?? null;
        if ($meta !== null) {
            return $meta;
        }

        if ($category === 'position_limits') {
            return [
                'label' => strtoupper($key).' position limit',
                'unit' => 'units',
                'description' => 'Maximum '.strtoupper($key).' holdings before escalation.',
            ];
        }

        return ['label' => $key, 'unit' => '', 'description' => ''];
    }

    /**
     * Ordering chains declared for a category.
     *
     * @return list<list<string>>
     */
    public static function orderedChains(string $category): array
    {
        return self::categories()[$category]['ordered'] ?? [];
    }
}
