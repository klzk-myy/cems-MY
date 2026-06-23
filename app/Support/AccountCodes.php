<?php

namespace App\Support;

use App\Enums\AccountCode;

class AccountCodes
{
    public static function code(AccountCode $account): string
    {
        return $account->value;
    }

    public static function description(AccountCode $account): string
    {
        return $account->description();
    }

    public static function category(AccountCode $account): string
    {
        return $account->category();
    }

    public static function isAsset(AccountCode $account): bool
    {
        return $account->category() === 'Asset';
    }

    public static function isLiability(AccountCode $account): bool
    {
        return $account->category() === 'Liability';
    }

    public static function isEquity(AccountCode $account): bool
    {
        return $account->category() === 'Equity';
    }

    public static function isRevenue(AccountCode $account): bool
    {
        return $account->category() === 'Revenue';
    }

    public static function isExpense(AccountCode $account): bool
    {
        return $account->category() === 'Expense';
    }

    public static function isOffBalance(AccountCode $account): bool
    {
        return $account->category() === 'Off-Balance';
    }

    public static function findByCode(string $code): ?AccountCode
    {
        foreach (AccountCode::cases() as $case) {
            if ($case->value === $code) {
                return $case;
            }
        }

        return null;
    }

    public static function cashAccounts(): array
    {
        return [
            AccountCode::CASH_MYR,
            AccountCode::CASH_USD,
            AccountCode::CASH_EUR,
            AccountCode::CASH_GBP,
            AccountCode::CASH_SGD,
            AccountCode::CASH_JPY,
            AccountCode::CASH_THB,
            AccountCode::CASH_AUD,
        ];
    }

    public static function bankAccounts(): array
    {
        return [
            AccountCode::BANK_MAYBANK,
            AccountCode::BANK_CIMB,
            AccountCode::BANK_PUBLIC,
            AccountCode::BANK_RHB,
        ];
    }

    public static function nostroAccounts(): array
    {
        return [
            AccountCode::NOSTRO_USD,
            AccountCode::NOSTRO_EUR,
            AccountCode::NOSTRO_GBP,
        ];
    }

    public static function forexInventoryAccounts(): array
    {
        return [
            AccountCode::FOREX_INVENTORY_USD,
            AccountCode::FOREX_INVENTORY_EUR,
            AccountCode::FOREX_INVENTORY_GBP,
            AccountCode::FOREX_INVENTORY_SGD,
            AccountCode::FOREX_INVENTORY_JPY,
            AccountCode::FOREX_INVENTORY_THB,
            AccountCode::FOREX_INVENTORY_AUD,
        ];
    }

    public static function revenueAccounts(): array
    {
        return [
            AccountCode::REVENUE_FOREX_TRADING,
            AccountCode::REVENUE_SPREAD,
            AccountCode::REVENUE_COMMISSION,
            AccountCode::REVENUE_REVALUATION_GAIN,
            AccountCode::REVENUE_FOREX_GAIN,
            AccountCode::REVENUE_INTEREST,
            AccountCode::REVENUE_OTHER,
        ];
    }

    public static function expenseAccounts(): array
    {
        return [
            AccountCode::COGS_CURRENCY,
            AccountCode::FOREX_LOSS,
            AccountCode::REVALUATION_LOSS,
            AccountCode::EXPENSE_SALARIES,
            AccountCode::EXPENSE_EPF,
            AccountCode::EXPENSE_EIS,
            AccountCode::EXPENSE_SOCSO,
            AccountCode::EXPENSE_RENT,
            AccountCode::EXPENSE_UTILITIES,
            AccountCode::EXPENSE_MAINTENANCE,
            AccountCode::EXPENSE_INSURANCE,
            AccountCode::EXPENSE_IT_INFRA,
            AccountCode::EXPENSE_SOFTWARE,
            AccountCode::EXPENSE_MARKETING,
            AccountCode::EXPENSE_TRAVEL,
            AccountCode::EXPENSE_COMMUNICATION,
            AccountCode::EXPENSE_OFFICE_SUPPLIES,
            AccountCode::EXPENSE_BANK_CHARGES,
            AccountCode::EXPENSE_INTEREST,
            AccountCode::EXPENSE_PROFESSIONAL,
            AccountCode::EXPENSE_AUDIT,
            AccountCode::EXPENSE_REGULATORY,
        ];
    }

    public static function equityAccounts(): array
    {
        return [
            AccountCode::CAPITAL_PAID_IN,
            AccountCode::SHARE_PREMIUM,
            AccountCode::STATUTORY_RESERVE,
            AccountCode::RETAINED_EARNINGS,
            AccountCode::UNREALIZED_FOREX,
            AccountCode::CURRENT_YEAR_EARNINGS,
            AccountCode::INCOME_SUMMARY,
            AccountCode::RETAINED_EARNINGS_CURRENT,
        ];
    }

    public static function offBalanceAccounts(): array
    {
        return [
            AccountCode::SPOT_CONTRACTS_USD,
            AccountCode::SPOT_CONTRACTS_EUR,
            AccountCode::SPOT_CONTRACTS_GBP,
            AccountCode::SPOT_CONTRACTS_SGD,
            AccountCode::FORWARD_CONTRACTS_USD,
            AccountCode::FORWARD_CONTRACTS_EUR,
            AccountCode::FORWARD_CONTRACTS_GBP,
            AccountCode::CONTINGENT_LC,
            AccountCode::GUARANTEES_GIVEN,
            AccountCode::GUARANTEES_RECEIVED,
        ];
    }

    public static function cashAndBankCodes(): array
    {
        $codes = [];
        foreach (self::cashAccounts() as $account) {
            $codes[] = $account->value;
        }
        foreach (self::bankAccounts() as $account) {
            $codes[] = $account->value;
        }

        return $codes;
    }

    public static function revenueCodes(): array
    {
        return array_map(fn ($a) => $a->value, self::revenueAccounts());
    }

    public static function expenseCodes(): array
    {
        return array_map(fn ($a) => $a->value, self::expenseAccounts());
    }
}
