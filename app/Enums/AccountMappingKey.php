<?php

namespace App\Enums;

/**
 * Account Mapping Key
 *
 * Fixed mapping keys resolved through the account_mappings table. Each case
 * knows its default account (used when no row exists and as the seeded value),
 * the expected account type for validation, and the legacy config key that
 * supplied the account before the mapping table existed.
 *
 * Dynamic per-currency keys (cash.{CCY}, inventory.{CCY}) are not enum cases —
 * AccountMappingService resolves them and falls back to the *.default keys here.
 */
enum AccountMappingKey: string
{
    case CashMyr = 'cash.myr';
    case CashPetty = 'cash.petty';
    case CashDefault = 'cash.default';
    case InventoryDefault = 'inventory.default';
    case RevenueForex = 'revenue.forex';
    case LossForex = 'loss.forex';
    case EquityCapital = 'equity.capital';
    case SuspenseHq = 'suspense.hq';
    case RevaluationPosition = 'revaluation.position';
    case RevaluationGain = 'revaluation.gain';
    case RevaluationLoss = 'revaluation.loss';
    case CloseRevenueSummary = 'close.revenue_summary';
    case CloseExpenseSummary = 'close.expense_summary';
    case CloseRetainedEarnings = 'close.retained_earnings';

    public function label(): string
    {
        return match ($this) {
            self::CashMyr => 'Cash — MYR',
            self::CashPetty => 'Petty Cash',
            self::CashDefault => 'Cash — Default (foreign legs)',
            self::InventoryDefault => 'Foreign Currency Inventory — Default',
            self::RevenueForex => 'Forex Trading Revenue',
            self::LossForex => 'Forex Loss',
            self::EquityCapital => 'Owner Equity / Capital',
            self::SuspenseHq => 'HQ Suspense (inter-branch clearing)',
            self::RevaluationPosition => 'Revaluation — Forex Position',
            self::RevaluationGain => 'Revaluation — Gain',
            self::RevaluationLoss => 'Revaluation — Loss',
            self::CloseRevenueSummary => 'Period Close — Revenue Summary',
            self::CloseExpenseSummary => 'Period Close — Expense Summary',
            self::CloseRetainedEarnings => 'Period Close — Retained Earnings',
        };
    }

    /**
     * Where the key is consumed — shown on the management page so admins know
     * which postings a change affects.
     */
    public function usedBy(): string
    {
        return match ($this) {
            self::CashMyr => 'Buy/sell MYR legs, petty-cash funding, opening balances, branch settlement',
            self::CashPetty => 'Branch expense postings (credit leg)',
            self::CashDefault => 'Fallback for cash.{CCY} overrides (manual journals, future foreign-cash legs)',
            self::InventoryDefault => 'Buy/sell FX inventory legs, opening stock, revaluation position, branch settlement',
            self::RevenueForex => 'Sell-side gain line on FX sales',
            self::LossForex => 'Sell-side loss line on FX sales',
            self::EquityCapital => 'Opening balance equity leg',
            self::SuspenseHq => 'Branch settlement HQ leg (branch closing workflow)',
            self::RevaluationPosition => 'Month-end revaluation position leg',
            self::RevaluationGain => 'Month-end revaluation gain leg',
            self::RevaluationLoss => 'Month-end revaluation loss leg',
            self::CloseRevenueSummary => 'Period/fiscal-year close revenue sweep',
            self::CloseExpenseSummary => 'Period/fiscal-year close expense sweep',
            self::CloseRetainedEarnings => 'Period/fiscal-year close net-income transfer',
        };
    }

    /**
     * Account type a mapped account must have. Enforced by the management
     * page and AccountMappingService::validateAccount.
     */
    public function expectedType(): AccountType
    {
        return match ($this) {
            self::CashMyr, self::CashPetty, self::CashDefault,
            self::InventoryDefault, self::SuspenseHq, self::RevaluationPosition => AccountType::Asset,
            self::RevenueForex, self::RevaluationGain => AccountType::Revenue,
            self::LossForex, self::RevaluationLoss => AccountType::Expense,
            self::EquityCapital,
            // Close summary accounts sweep into Income Summary / Retained
            // Earnings — both are Equity-typed in the chart.
            self::CloseRevenueSummary, self::CloseExpenseSummary,
            self::CloseRetainedEarnings => AccountType::Equity,
        };
    }

    public function defaultAccount(): AccountCode
    {
        return match ($this) {
            self::CashMyr, self::CashDefault => AccountCode::CASH_MYR,
            self::CashPetty => AccountCode::PETTY_CASH,
            self::InventoryDefault, self::RevaluationPosition => AccountCode::FOREIGN_CURRENCY_INVENTORY,
            self::RevenueForex => AccountCode::FOREX_TRADING_REVENUE,
            self::LossForex => AccountCode::FOREX_LOSS,
            self::EquityCapital => AccountCode::CAPITAL,
            self::SuspenseHq => AccountCode::INTER_BRANCH_CLEARING,
            self::RevaluationGain => AccountCode::REVENUE_REVALUATION_GAIN,
            self::RevaluationLoss => AccountCode::REVALUATION_LOSS,
            self::CloseRevenueSummary, self::CloseExpenseSummary => AccountCode::INCOME_SUMMARY,
            self::CloseRetainedEarnings => AccountCode::RETAINED_EARNINGS,
        };
    }

    /**
     * config/accounting.php key that supplied this account before the mapping
     * table existed. Read once at seed time so existing .env overrides carry
     * forward; runtime resolution never touches config.
     */
    public function legacyConfigKey(): ?string
    {
        return match ($this) {
            self::RevaluationPosition => 'accounting.forex_position_account',
            self::RevaluationGain => 'accounting.revaluation_gain_account',
            self::RevaluationLoss => 'accounting.revaluation_loss_account',
            self::CloseRevenueSummary => 'accounting.revenue_summary_account',
            self::CloseExpenseSummary => 'accounting.expense_summary_account',
            self::CloseRetainedEarnings => 'accounting.retained_earnings_account',
            default => null,
        };
    }

    /**
     * UI grouping for the management page.
     */
    public function section(): string
    {
        return match ($this) {
            self::CashMyr, self::CashPetty, self::CashDefault,
            self::InventoryDefault => 'Cash & Inventory',
            self::RevenueForex, self::LossForex => 'Trading P&L',
            self::EquityCapital => 'Equity',
            self::SuspenseHq => 'Settlement',
            self::RevaluationPosition, self::RevaluationGain, self::RevaluationLoss => 'Revaluation',
            self::CloseRevenueSummary, self::CloseExpenseSummary, self::CloseRetainedEarnings => 'Period Close',
        };
    }
}
