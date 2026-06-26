<?php

namespace App\Enums;

enum ReportType: string
{
    case Msb2 = 'msb2';
    case Lmca = 'lmca';
    case Qlvr = 'qlvr';
    case Plr = 'plr';
    case TrialBalance = 'trial_balance';
    case MonthEnd = 'month_end';
    case ProfitLoss = 'profit_loss';
    case BalanceSheet = 'balance_sheet';

    public function label(): string
    {
        return match ($this) {
            self::Msb2 => 'MSB2',
            self::Lmca => 'LMCA',
            self::Qlvr => 'QLVR',
            self::Plr => 'Position Limit Report',
            self::TrialBalance => 'Trial Balance',
            self::MonthEnd => 'Month End',
            self::ProfitLoss => 'Profit & Loss',
            self::BalanceSheet => 'Balance Sheet',
        };
    }

    /**
     * Machine-safe identifier used in generated filenames.
     * Preserves the legacy naming convention where possible.
     */
    public function filenameKey(): string
    {
        return match ($this) {
            self::Msb2 => 'MSB2',
            self::Lmca => 'LMCA',
            self::Qlvr => 'QLVR',
            self::Plr => 'PLR',
            self::TrialBalance => 'TrialBalance',
            self::MonthEnd => 'MONTH_END',
            self::ProfitLoss => 'PL',
            self::BalanceSheet => 'BalanceSheet',
        };
    }

    /**
     * Comma-separated list of all canonical values for validation rules.
     */
    public static function validationRule(): string
    {
        return implode(',', array_map(fn (self $type) => $type->value, self::cases()));
    }
}
