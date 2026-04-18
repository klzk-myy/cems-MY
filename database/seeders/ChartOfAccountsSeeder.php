<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // === ASSETS (1000-1999) ===

            // Cash accounts by currency (1000-1099)
            ['account_code' => '1000', 'account_name' => 'Cash - MYR (Ringgit)', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1010', 'account_name' => 'Cash - USD', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1011', 'account_name' => 'Cash - EUR', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1012', 'account_name' => 'Cash - GBP', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1013', 'account_name' => 'Cash - SGD', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1014', 'account_name' => 'Cash - AUD', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1015', 'account_name' => 'Cash - JPY', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1016', 'account_name' => 'Cash - CHF', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1017', 'account_name' => 'Cash - CAD', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1018', 'account_name' => 'Cash - HKD', 'account_type' => 'Asset', 'parent_code' => null],

            // Nostro accounts (1100-1199) - foreign currency holdings with correspondents
            ['account_code' => '1110', 'account_name' => 'Nostro USD - Wells Fargo', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1111', 'account_name' => 'Nostro EUR - Deutsche Bank', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1112', 'account_name' => 'Nostro GBP - HSBC London', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1113', 'account_name' => 'Nostro SGD - DBS Singapore', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1114', 'account_name' => 'Nostro AUD - Westpac', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1115', 'account_name' => 'Nostro JPY - Mizuho', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1116', 'account_name' => 'Nostro CHF - UBS Zurich', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1117', 'account_name' => 'Nostro CAD - RBC Toronto', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1118', 'account_name' => 'Nostro HKD - HSBC HK', 'account_type' => 'Asset', 'parent_code' => null],

            // Other cash and liquid assets (1120-1199)
            ['account_code' => '1120', 'account_name' => 'Petty Cash', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1130', 'account_name' => 'Cash in Transit', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1140', 'account_name' => 'Overnight Deposits', 'account_type' => 'Asset', 'parent_code' => null],

            // Other current assets (1200-1499)
            ['account_code' => '1200', 'account_name' => 'Fixed Assets - Computer Equipment', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1201', 'account_name' => 'Fixed Assets - Office Furniture', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1202', 'account_name' => 'Fixed Assets - Motor Vehicles', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1210', 'account_name' => 'Accumulated Depreciation - Fixed Assets', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1300', 'account_name' => 'Foreign Currency Inventory', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1400', 'account_name' => 'Accounts Receivable', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1410', 'account_name' => 'Interest Receivable', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1500', 'account_name' => 'Prepaid Expenses', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1510', 'account_name' => 'Prepaid Insurance', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1520', 'account_name' => 'Prepaid Rent', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1600', 'account_name' => 'Deposits - BNM', 'account_type' => 'Asset', 'parent_code' => null],

            // Suspense and clearing accounts (1700-1899)
            ['account_code' => '1700', 'account_name' => 'Suspense Account', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1710', 'account_name' => 'Unallocated Cash', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1800', 'account_name' => 'Clearing Account - Transactions', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1810', 'account_name' => 'Suspense - Pending Investigation', 'account_type' => 'Asset', 'parent_code' => null],
            ['account_code' => '1900', 'account_name' => 'VAT Input Tax', 'account_type' => 'Asset', 'parent_code' => null],

            // === LIABILITIES (2000-2999) ===

            ['account_code' => '2000', 'account_name' => 'Accounts Payable', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2010', 'account_name' => 'Accounts Payable - Suppliers', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2100', 'account_name' => 'Customer Escrow - MYR', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2101', 'account_name' => 'Customer Escrow - USD', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2102', 'account_name' => 'Customer Escrow - EUR', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2110', 'account_name' => 'Regulatory Reserve - BNM', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2200', 'account_name' => 'Accrued Expenses', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2210', 'account_name' => 'Accrued Salaries', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2220', 'account_name' => 'Accrued Audit Fees', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2300', 'account_name' => 'Interest Payable', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2400', 'account_name' => 'GST Payable', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2500', 'account_name' => 'Withholding Tax Payable', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2600', 'account_name' => 'Security Deposits', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2700', 'account_name' => 'Staff Deposits', 'account_type' => 'Liability', 'parent_code' => null],
            ['account_code' => '2800', 'account_name' => 'Provision for Redundancy', 'account_type' => 'Liability', 'parent_code' => null],

            // === EQUITY (3000-3999) ===

            ['account_code' => '3000', 'account_name' => 'Paid-in Capital', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '3100', 'account_name' => 'Share Capital', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '3110', 'account_name' => 'Asset Revaluation Reserve', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '3120', 'account_name' => 'Forex Translation Reserve', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '4000', 'account_name' => 'Retained Earnings - Prior', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '4010', 'account_name' => 'Retained Earnings - Current', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '4100', 'account_name' => 'Unrealized Forex Gains/Losses', 'account_type' => 'Equity', 'parent_code' => null],
            ['account_code' => '4200', 'account_name' => 'Dividend Payable', 'account_type' => 'Equity', 'parent_code' => null],

            // === REVENUE (4000-4999) ===

            ['account_code' => '5000', 'account_name' => 'Revenue - Forex Trading (Buy/Sell)', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5001', 'account_name' => 'Revenue - Commission', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5002', 'account_name' => 'Revenue - Spread Income', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5003', 'account_name' => 'Revenue - Transfer Fees', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5100', 'account_name' => 'Revenue - Revaluation Gain', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5110', 'account_name' => 'Interest Income', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5120', 'account_name' => 'Revenue - Other', 'account_type' => 'Revenue', 'parent_code' => null],
            ['account_code' => '5200', 'account_name' => 'Income - Fines and Penalties', 'account_type' => 'Revenue', 'parent_code' => null],

            // === EXPENSE (5000-5999) ===

            ['account_code' => '6000', 'account_name' => 'Expense - Forex Loss (Trading)', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6010', 'account_name' => 'Expense - Revaluation Loss', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6100', 'account_name' => 'Expense - Salary and Wages', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6110', 'account_name' => 'Expense - Employer EPF', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6120', 'account_name' => 'Expense - Staff Benefits', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6130', 'account_name' => 'Expense - Staff Training', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6200', 'account_name' => 'Expense - Rent', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6210', 'account_name' => 'Expense - Utilities', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6220', 'account_name' => 'Expense - Office Supplies', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6230', 'account_name' => 'Expense - Insurance', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6240', 'account_name' => 'Expense - IT Services', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6250', 'account_name' => 'Expense - IT Equipment', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6300', 'account_name' => 'Expense - Audit Fees', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6310', 'account_name' => 'Expense - Legal Fees', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6400', 'account_name' => 'Expense - BNM License Fee', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6410', 'account_name' => 'Expense - Compliance Costs', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6420', 'account_name' => 'Expense - AML Screening Costs', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6500', 'account_name' => 'Expense - Marketing', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6600', 'account_name' => 'Expense - Travel', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6700', 'account_name' => 'Expense - Bank Charges', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6800', 'account_name' => 'Expense - Depreciation', 'account_type' => 'Expense', 'parent_code' => null],
            ['account_code' => '6900', 'account_name' => 'Expense - Other', 'account_type' => 'Expense', 'parent_code' => null],

            // === OFF-BALANCE SHEET (6000+) ===

            ['account_code' => '7000', 'account_name' => 'Outstanding Spot Contracts - USD', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7001', 'account_name' => 'Outstanding Spot Contracts - EUR', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7002', 'account_name' => 'Outstanding Spot Contracts - GBP', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7010', 'account_name' => 'Outstanding Forward Contracts - USD', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7011', 'account_name' => 'Outstanding Forward Contracts - EUR', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7012', 'account_name' => 'Outstanding Forward Contracts - GBP', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7100', 'account_name' => 'Contingent Liabilities - LC', 'account_type' => 'Off-Balance', 'parent_code' => null],
            ['account_code' => '7200', 'account_name' => 'Guarantees Given', 'account_type' => 'Off-Balance', 'parent_code' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }

        $this->command->info('Seeded '.count($accounts).' chart of accounts');
    }
}
