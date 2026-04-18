<?php

namespace App\Enums;

/**
 * Chart of Account Codes Enum
 *
 * Maps account codes used in double-entry accounting.
 * Based on the 18 default accounts structure.
 */
enum AccountCode: string
{
    // Asset accounts (1000-2200)
    case CASH_MYR = '1000';
    case FOREIGN_CURRENCY_INVENTORY = '2000';
    case RECEIVABLES = '2100';
    case OTHER_CURRENT_ASSETS = '2200';

    // Liability accounts (3000-3100)
    case PAYABLES = '3000';
    case ACCRUALS = '3100';

    // Equity accounts (4000-4200)
    case CAPITAL = '4000';
    case RETAINED_EARNINGS = '4100';
    case CURRENT_YEAR_EARNINGS = '4200';

    // Revenue accounts (5000-5100)
    case FOREX_TRADING_REVENUE = '5000';
    case REVALUATION_GAINS = '5100';

    // Expense accounts (6000-6200)
    case FOREX_LOSS = '6000';
    case REVALUATION_LOSS = '6100';
    case OPERATING_EXPENSES = '6200';

    /**
     * Get account type category.
     */
    public function category(): string
    {
        return match ($this) {
            self::CASH_MYR, self::FOREIGN_CURRENCY_INVENTORY, self::RECEIVABLES, self::OTHER_CURRENT_ASSETS => 'Asset',
            self::PAYABLES, self::ACCRUALS => 'Liability',
            self::CAPITAL, self::RETAINED_EARNINGS, self::CURRENT_YEAR_EARNINGS => 'Equity',
            self::FOREX_TRADING_REVENUE, self::REVALUATION_GAINS => 'Revenue',
            self::FOREX_LOSS, self::REVALUATION_LOSS, self::OPERATING_EXPENSES => 'Expense',
        };
    }

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::CASH_MYR => 'Cash (MYR)',
            self::FOREIGN_CURRENCY_INVENTORY => 'Foreign Currency Inventory',
            self::RECEIVABLES => 'Accounts Receivable',
            self::OTHER_CURRENT_ASSETS => 'Other Current Assets',
            self::PAYABLES => 'Accounts Payable',
            self::ACCRUALS => 'Accruals',
            self::CAPITAL => 'Capital',
            self::RETAINED_EARNINGS => 'Retained Earnings',
            self::CURRENT_YEAR_EARNINGS => 'Current Year Earnings',
            self::FOREX_TRADING_REVENUE => 'Forex Trading Revenue',
            self::REVALUATION_GAINS => 'Revaluation Gains',
            self::FOREX_LOSS => 'Forex Loss',
            self::REVALUATION_LOSS => 'Revaluation Loss',
            self::OPERATING_EXPENSES => 'Operating Expenses',
        };
    }
}
