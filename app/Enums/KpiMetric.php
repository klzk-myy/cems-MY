<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The BI metric catalog. One definition drives the dashboard card, the
 * threshold-alert form and its validation, and the drill-down route.
 *
 * The case values are the exact strings `KpiService::summarize()` returns
 * and `bi_alert_rules.metric` stores, so the catalog is the single source
 * of truth rather than a parallel list that can drift.
 */
enum KpiMetric: string
{
    case VolumeMyr = 'volume_myr';
    case TxCount = 'tx_count';
    case AvgTicketMyr = 'avg_ticket_myr';
    case RealizedMarginMyr = 'realized_margin_myr';
    case MarginPct = 'margin_pct';
    case UniqueCustomers = 'unique_customers';
    case CancelRatePct = 'cancel_rate_pct';
    case RejectRatePct = 'reject_rate_pct';
    case AvgApprovalSeconds = 'avg_approval_seconds';
    case RateOverrideCount = 'rate_override_count';

    public function label(): string
    {
        return match ($this) {
            self::VolumeMyr => 'Volume (MYR)',
            self::TxCount => 'Transactions',
            self::AvgTicketMyr => 'Average ticket (MYR)',
            self::RealizedMarginMyr => 'Realized margin (MYR)',
            self::MarginPct => 'Margin %',
            self::UniqueCustomers => 'Unique customers',
            self::CancelRatePct => 'Cancel rate %',
            self::RejectRatePct => 'Reject rate %',
            self::AvgApprovalSeconds => 'Avg approval time (s)',
            self::RateOverrideCount => 'Rate overrides',
        };
    }

    /**
     * Display unit, so callers format a value without re-deriving it.
     */
    public function unit(): string
    {
        return match ($this) {
            self::VolumeMyr, self::AvgTicketMyr, self::RealizedMarginMyr => 'myr',
            self::MarginPct, self::CancelRatePct, self::RejectRatePct => 'percent',
            self::AvgApprovalSeconds => 'seconds',
            self::TxCount, self::UniqueCustomers, self::RateOverrideCount => 'count',
        };
    }

    /**
     * Where clicking a card or alert leads — the BI page carrying this
     * metric's detail view.
     */
    public function drillRoute(): string
    {
        return match ($this) {
            self::VolumeMyr => 'bi.branches',
            self::RealizedMarginMyr, self::MarginPct => 'bi.currencies',
            self::TxCount, self::CancelRatePct, self::RejectRatePct, self::AvgApprovalSeconds => 'bi.operations',
            self::AvgTicketMyr, self::UniqueCustomers => 'bi.customers',
            self::RateOverrideCount => 'bi.rates',
        };
    }

    /**
     * @return array<string, string> metric key => label, the alert-rule form options
     */
    public static function labelMap(): array
    {
        return array_combine(
            array_map(fn (self $metric) => $metric->value, self::cases()),
            array_map(fn (self $metric) => $metric->label(), self::cases()),
        );
    }

    /**
     * @return array<string, string> metric key => drill route
     */
    public static function drillRouteMap(): array
    {
        return array_combine(
            array_map(fn (self $metric) => $metric->value, self::cases()),
            array_map(fn (self $metric) => $metric->drillRoute(), self::cases()),
        );
    }
}
