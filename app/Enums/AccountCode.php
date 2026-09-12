<?php

namespace App\Enums;

enum AccountCode: string
{
    case CASH_MYR = '1000';
    case CASH_USD = '1001';
    case CASH_EUR = '1002';
    case CASH_GBP = '1003';
    case CASH_SGD = '1004';
    case CASH_JPY = '1005';
    case CASH_THB = '1006';
    case CASH_AUD = '1007';

    case PETTY_CASH = '1050';

    case BANK_MAYBANK = '1100';
    case BANK_CIMB = '1101';
    case BANK_PUBLIC = '1102';
    case BANK_RHB = '1103';

    case NOSTRO_USD = '1200';
    case NOSTRO_EUR = '1201';
    case NOSTRO_GBP = '1202';

    case FOREIGN_CURRENCY_INVENTORY = '2000';
    case FOREX_INVENTORY_USD = '2001';
    case FOREX_INVENTORY_EUR = '2002';
    case FOREX_INVENTORY_GBP = '2003';
    case FOREX_INVENTORY_SGD = '2004';
    case FOREX_INVENTORY_JPY = '2005';
    case FOREX_INVENTORY_THB = '2006';
    case FOREX_INVENTORY_AUD = '2007';

    case RECEIVABLES = '2100';
    case OTHER_CURRENT_ASSETS = '2200';

    case PAYABLES = '3000';
    case ACCRUALS = '3100';

    case CAPITAL = '4000';
    case CAPITAL_PAID_IN = '4001';
    case SHARE_PREMIUM = '4002';
    case STATUTORY_RESERVE = '4003';
    case RETAINED_EARNINGS = '4100';
    case RETAINED_EARNINGS_CURRENT = '4101';
    case UNREALIZED_FOREX = '4102';
    case CURRENT_YEAR_EARNINGS = '4200';
    case INCOME_SUMMARY = '4201';

    case FOREX_TRADING_REVENUE = '5000';
    case REVENUE_FOREX_TRADING = '5001';
    case REVENUE_SPREAD = '5002';
    case REVENUE_COMMISSION = '5003';
    case REVENUE_REVALUATION_GAIN = '5100';
    case REVENUE_FOREX_GAIN = '5101';
    case REVENUE_INTEREST = '5102';
    case REVENUE_OTHER = '5103';
    case REVALUATION_GAINS = '5110';

    case COGS_CURRENCY = '6000';
    case FOREX_LOSS = '6001';
    case REVALUATION_LOSS = '6100';
    case EXPENSE_SALARIES = '6200';
    case EXPENSE_EPF = '6201';
    case EXPENSE_EIS = '6202';
    case EXPENSE_SOCSO = '6203';
    case EXPENSE_RENT = '6204';
    case EXPENSE_UTILITIES = '6205';
    case EXPENSE_MAINTENANCE = '6206';
    case EXPENSE_INSURANCE = '6207';
    case EXPENSE_IT_INFRA = '6208';
    case EXPENSE_SOFTWARE = '6209';
    case EXPENSE_MARKETING = '6210';
    case EXPENSE_TRAVEL = '6211';
    case EXPENSE_COMMUNICATION = '6212';
    case EXPENSE_OFFICE_SUPPLIES = '6213';
    case EXPENSE_BANK_CHARGES = '6214';
    case EXPENSE_INTEREST = '6215';
    case EXPENSE_PROFESSIONAL = '6216';
    case EXPENSE_AUDIT = '6217';
    case EXPENSE_REGULATORY = '6218';
    case OPERATING_EXPENSES = '6299';

    case SPOT_CONTRACTS_USD = '9001';
    case SPOT_CONTRACTS_EUR = '9002';
    case SPOT_CONTRACTS_GBP = '9003';
    case SPOT_CONTRACTS_SGD = '9004';
    case FORWARD_CONTRACTS_USD = '9101';
    case FORWARD_CONTRACTS_EUR = '9102';
    case FORWARD_CONTRACTS_GBP = '9103';
    case CONTINGENT_LC = '9200';
    case GUARANTEES_GIVEN = '9201';
    case GUARANTEES_RECEIVED = '9202';

    public function category(): string
    {
        return match ($this) {
            self::CASH_MYR, self::CASH_USD, self::CASH_EUR, self::CASH_GBP, self::CASH_SGD, self::CASH_JPY, self::CASH_THB, self::CASH_AUD, self::PETTY_CASH,
            self::BANK_MAYBANK, self::BANK_CIMB, self::BANK_PUBLIC, self::BANK_RHB,
            self::NOSTRO_USD, self::NOSTRO_EUR, self::NOSTRO_GBP,
            self::FOREIGN_CURRENCY_INVENTORY, self::FOREX_INVENTORY_USD, self::FOREX_INVENTORY_EUR, self::FOREX_INVENTORY_GBP,
            self::FOREX_INVENTORY_SGD, self::FOREX_INVENTORY_JPY, self::FOREX_INVENTORY_THB, self::FOREX_INVENTORY_AUD,
            self::RECEIVABLES, self::OTHER_CURRENT_ASSETS => 'Asset',
            self::PAYABLES, self::ACCRUALS => 'Liability',
            self::CAPITAL, self::CAPITAL_PAID_IN, self::SHARE_PREMIUM, self::STATUTORY_RESERVE,
            self::RETAINED_EARNINGS, self::RETAINED_EARNINGS_CURRENT, self::UNREALIZED_FOREX,
            self::CURRENT_YEAR_EARNINGS, self::INCOME_SUMMARY => 'Equity',
            self::FOREX_TRADING_REVENUE, self::REVENUE_FOREX_TRADING, self::REVENUE_SPREAD, self::REVENUE_COMMISSION,
            self::REVENUE_REVALUATION_GAIN, self::REVENUE_FOREX_GAIN, self::REVENUE_INTEREST, self::REVENUE_OTHER,
            self::REVALUATION_GAINS => 'Revenue',
            self::COGS_CURRENCY, self::FOREX_LOSS, self::REVALUATION_LOSS,
            self::EXPENSE_SALARIES, self::EXPENSE_EPF, self::EXPENSE_EIS, self::EXPENSE_SOCSO,
            self::EXPENSE_RENT, self::EXPENSE_UTILITIES, self::EXPENSE_MAINTENANCE, self::EXPENSE_INSURANCE,
            self::EXPENSE_IT_INFRA, self::EXPENSE_SOFTWARE, self::EXPENSE_MARKETING, self::EXPENSE_TRAVEL,
            self::EXPENSE_COMMUNICATION, self::EXPENSE_OFFICE_SUPPLIES, self::EXPENSE_BANK_CHARGES,
            self::EXPENSE_INTEREST, self::EXPENSE_PROFESSIONAL, self::EXPENSE_AUDIT, self::EXPENSE_REGULATORY,
            self::OPERATING_EXPENSES => 'Expense',
            self::SPOT_CONTRACTS_USD, self::SPOT_CONTRACTS_EUR, self::SPOT_CONTRACTS_GBP, self::SPOT_CONTRACTS_SGD,
            self::FORWARD_CONTRACTS_USD, self::FORWARD_CONTRACTS_EUR, self::FORWARD_CONTRACTS_GBP,
            self::CONTINGENT_LC, self::GUARANTEES_GIVEN, self::GUARANTEES_RECEIVED => 'Off-Balance',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CASH_MYR => 'Cash (MYR)',
            self::CASH_USD => 'Cash (USD)',
            self::CASH_EUR => 'Cash (EUR)',
            self::CASH_GBP => 'Cash (GBP)',
            self::CASH_SGD => 'Cash (SGD)',
            self::CASH_JPY => 'Cash (JPY)',
            self::CASH_THB => 'Cash (THB)',
            self::CASH_AUD => 'Cash (AUD)',
            self::PETTY_CASH => 'Petty Cash',
            self::BANK_MAYBANK => 'Bank (Maybank)',
            self::BANK_CIMB => 'Bank (CIMB)',
            self::BANK_PUBLIC => 'Bank (Public)',
            self::BANK_RHB => 'Bank (RHB)',
            self::NOSTRO_USD => 'Nostro (USD)',
            self::NOSTRO_EUR => 'Nostro (EUR)',
            self::NOSTRO_GBP => 'Nostro (GBP)',
            self::FOREIGN_CURRENCY_INVENTORY => 'Foreign Currency Inventory',
            self::FOREX_INVENTORY_USD => 'Forex Inventory (USD)',
            self::FOREX_INVENTORY_EUR => 'Forex Inventory (EUR)',
            self::FOREX_INVENTORY_GBP => 'Forex Inventory (GBP)',
            self::FOREX_INVENTORY_SGD => 'Forex Inventory (SGD)',
            self::FOREX_INVENTORY_JPY => 'Forex Inventory (JPY)',
            self::FOREX_INVENTORY_THB => 'Forex Inventory (THB)',
            self::FOREX_INVENTORY_AUD => 'Forex Inventory (AUD)',
            self::RECEIVABLES => 'Accounts Receivable',
            self::OTHER_CURRENT_ASSETS => 'Other Current Assets',
            self::PAYABLES => 'Accounts Payable',
            self::ACCRUALS => 'Accruals',
            self::CAPITAL => 'Capital',
            self::CAPITAL_PAID_IN => 'Capital Paid-In',
            self::SHARE_PREMIUM => 'Share Premium',
            self::STATUTORY_RESERVE => 'Statutory Reserve',
            self::RETAINED_EARNINGS => 'Retained Earnings',
            self::RETAINED_EARNINGS_CURRENT => 'Retained Earnings (Current)',
            self::UNREALIZED_FOREX => 'Unrealized Forex',
            self::CURRENT_YEAR_EARNINGS => 'Current Year Earnings',
            self::INCOME_SUMMARY => 'Income Summary',
            self::FOREX_TRADING_REVENUE => 'Forex Trading Revenue',
            self::REVENUE_FOREX_TRADING => 'Revenue - Forex Trading',
            self::REVENUE_SPREAD => 'Revenue - Spread',
            self::REVENUE_COMMISSION => 'Revenue - Commission',
            self::REVENUE_REVALUATION_GAIN => 'Revenue - Revaluation Gain',
            self::REVENUE_FOREX_GAIN => 'Revenue - Forex Gain',
            self::REVENUE_INTEREST => 'Revenue - Interest',
            self::REVENUE_OTHER => 'Revenue - Other',
            self::REVALUATION_GAINS => 'Revaluation Gains',
            self::COGS_CURRENCY => 'COGS - Currency',
            self::FOREX_LOSS => 'Forex Loss',
            self::REVALUATION_LOSS => 'Revaluation Loss',
            self::EXPENSE_SALARIES => 'Salaries',
            self::EXPENSE_EPF => 'EPF',
            self::EXPENSE_EIS => 'EIS',
            self::EXPENSE_SOCSO => 'SOCSO',
            self::EXPENSE_RENT => 'Rent',
            self::EXPENSE_UTILITIES => 'Utilities',
            self::EXPENSE_MAINTENANCE => 'Maintenance',
            self::EXPENSE_INSURANCE => 'Insurance',
            self::EXPENSE_IT_INFRA => 'IT Infrastructure',
            self::EXPENSE_SOFTWARE => 'Software',
            self::EXPENSE_MARKETING => 'Marketing',
            self::EXPENSE_TRAVEL => 'Travel',
            self::EXPENSE_COMMUNICATION => 'Communication',
            self::EXPENSE_OFFICE_SUPPLIES => 'Office Supplies',
            self::EXPENSE_BANK_CHARGES => 'Bank Charges',
            self::EXPENSE_INTEREST => 'Interest Expense',
            self::EXPENSE_PROFESSIONAL => 'Professional Fees',
            self::EXPENSE_AUDIT => 'Audit Fees',
            self::EXPENSE_REGULATORY => 'Regulatory Fees',
            self::OPERATING_EXPENSES => 'Operating Expenses',
            self::SPOT_CONTRACTS_USD => 'Spot Contracts (USD)',
            self::SPOT_CONTRACTS_EUR => 'Spot Contracts (EUR)',
            self::SPOT_CONTRACTS_GBP => 'Spot Contracts (GBP)',
            self::SPOT_CONTRACTS_SGD => 'Spot Contracts (SGD)',
            self::FORWARD_CONTRACTS_USD => 'Forward Contracts (USD)',
            self::FORWARD_CONTRACTS_EUR => 'Forward Contracts (EUR)',
            self::FORWARD_CONTRACTS_GBP => 'Forward Contracts (GBP)',
            self::CONTINGENT_LC => 'Contingent Letters of Credit',
            self::GUARANTEES_GIVEN => 'Guarantees Given',
            self::GUARANTEES_RECEIVED => 'Guarantees Received',
        };
    }
}
