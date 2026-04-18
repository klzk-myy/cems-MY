<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use Illuminate\Database\Seeder;

class EnhancedChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->createCashAccounts();
        $this->createReceivableAccounts();
        $this->createInventoryAccounts();
        $this->createPayableAccounts();
        $this->createAccruedAccounts();
        $this->createCapitalAccounts();
        $this->createRetainedEarningsAccounts();
        $this->createRevenueAccounts();
        $this->createExpenseAccounts();
        $this->createIncomeSummaryAccounts();
    }

    private function createCashAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();
        $finCc = CostCenter::where('code', 'FIN-002')->first();

        $accounts = [
            ['account_code' => '1000', 'account_name' => 'Cash - MYR', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1010', 'account_name' => 'Cash - USD', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1020', 'account_name' => 'Cash - EUR', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1030', 'account_name' => 'Cash - GBP', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1040', 'account_name' => 'Cash - SGD', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1050', 'account_name' => 'Cash - JPY', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1060', 'account_name' => 'Cash - THB', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1070', 'account_name' => 'Cash - AUD', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1100', 'account_name' => 'Bank - Maybank', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1110', 'account_name' => 'Bank - CIMB', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1120', 'account_name' => 'Bank - Public Bank', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $finCc?->id],
            ['account_code' => '1130', 'account_name' => 'Bank - RHB', 'account_type' => 'Asset', 'account_class' => 'Cash', 'cost_center_id' => $finCc?->id],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createReceivableAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();

        $accounts = [
            ['account_code' => '1500', 'account_name' => 'Accounts Receivable - Corporate', 'account_type' => 'Asset', 'account_class' => 'Receivable', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1510', 'account_name' => 'Accounts Receivable - Retail', 'account_type' => 'Asset', 'account_class' => 'Receivable', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '1520', 'account_name' => 'Accounts Receivable - Intercompany', 'account_type' => 'Asset', 'account_class' => 'Receivable', 'cost_center_id' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createInventoryAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();

        $accounts = [
            ['account_code' => '2000', 'account_name' => 'Foreign Currency Inventory - USD', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2010', 'account_name' => 'Foreign Currency Inventory - EUR', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2020', 'account_name' => 'Foreign Currency Inventory - GBP', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2030', 'account_name' => 'Foreign Currency Inventory - SGD', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2040', 'account_name' => 'Foreign Currency Inventory - JPY', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2050', 'account_name' => 'Foreign Currency Inventory - THB', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2060', 'account_name' => 'Foreign Currency Inventory - AUD', 'account_type' => 'Asset', 'account_class' => 'Inventory', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '2100', 'account_name' => 'Prepaid Expenses', 'account_type' => 'Asset', 'account_class' => 'Prepaid', 'cost_center_id' => null],
            ['account_code' => '2200', 'account_name' => 'Security Deposits', 'account_type' => 'Asset', 'account_class' => 'Asset', 'cost_center_id' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createPayableAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();
        $finCc = CostCenter::where('code', 'FIN-001')->first();

        $accounts = [
            ['account_code' => '3000', 'account_name' => 'Accounts Payable - Suppliers', 'account_type' => 'Liability', 'account_class' => 'Payable', 'cost_center_id' => $finCc?->id],
            ['account_code' => '3010', 'account_name' => 'Accounts Payable - Vendors', 'account_type' => 'Liability', 'account_class' => 'Payable', 'cost_center_id' => $finCc?->id],
            ['account_code' => '3020', 'account_name' => 'Accounts Payable - Intercompany', 'account_type' => 'Liability', 'account_class' => 'Payable', 'cost_center_id' => null],
            ['account_code' => '3100', 'account_name' => 'Suspense Account - FX', 'account_type' => 'Liability', 'account_class' => 'Payable', 'cost_center_id' => $opsCc?->id],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createAccruedAccounts(): void
    {
        $finCc = CostCenter::where('code', 'FIN-001')->first();

        $accounts = [
            ['account_code' => '3500', 'account_name' => 'Accrued Expenses - Salaries', 'account_type' => 'Liability', 'account_class' => 'Accrued', 'cost_center_id' => $finCc?->id],
            ['account_code' => '3510', 'account_name' => 'Accrued Expenses - Rent', 'account_type' => 'Liability', 'account_class' => 'Accrued', 'cost_center_id' => $finCc?->id],
            ['account_code' => '3520', 'account_name' => 'Accrued Expenses - Utilities', 'account_type' => 'Liability', 'account_class' => 'Accrued', 'cost_center_id' => $finCc?->id],
            ['account_code' => '3530', 'account_name' => 'Accrued Expenses - Interest', 'account_type' => 'Liability', 'account_class' => 'Accrued', 'cost_center_id' => $finCc?->id],
            ['account_code' => '3600', 'account_name' => 'Deferred Revenue', 'account_type' => 'Liability', 'account_class' => 'Liability', 'cost_center_id' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createCapitalAccounts(): void
    {
        $execCc = CostCenter::where('code', 'EXEC-001')->first();

        $accounts = [
            ['account_code' => '4000', 'account_name' => 'Paid-in Capital', 'account_type' => 'Equity', 'account_class' => 'Capital', 'cost_center_id' => $execCc?->id],
            ['account_code' => '4010', 'account_name' => 'Share Premium', 'account_type' => 'Equity', 'account_class' => 'Capital', 'cost_center_id' => null],
            ['account_code' => '4020', 'account_name' => 'Statutory Reserve', 'account_type' => 'Equity', 'account_class' => 'Capital', 'cost_center_id' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createRetainedEarningsAccounts(): void
    {
        $finCc = CostCenter::where('code', 'FIN-001')->first();

        $accounts = [
            ['account_code' => '4100', 'account_name' => 'Retained Earnings', 'account_type' => 'Equity', 'account_class' => 'Retained', 'cost_center_id' => $finCc?->id],
            ['account_code' => '4200', 'account_name' => 'Unrealized Forex Gains/Losses', 'account_type' => 'Equity', 'account_class' => 'Equity', 'cost_center_id' => null],
            ['account_code' => '4300', 'account_name' => 'Current Year Profit/Loss', 'account_type' => 'Equity', 'account_class' => 'Current Year', 'cost_center_id' => $finCc?->id],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createRevenueAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();
        $salesCc = CostCenter::where('code', 'SALES-001')->first();

        $accounts = [
            ['account_code' => '5000', 'account_name' => 'Revenue - Forex Trading', 'account_type' => 'Revenue', 'account_class' => 'Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '5010', 'account_name' => 'Revenue - Spread Income', 'account_type' => 'Revenue', 'account_class' => 'Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '5020', 'account_name' => 'Revenue - Commission', 'account_type' => 'Revenue', 'account_class' => 'Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '5100', 'account_name' => 'Revenue - Revaluation Gain', 'account_type' => 'Revenue', 'account_class' => 'Non-Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '5200', 'account_name' => 'Revenue - Foreign Exchange Gain', 'account_type' => 'Revenue', 'account_class' => 'Non-Operating', 'cost_center_id' => null],
            ['account_code' => '5300', 'account_name' => 'Revenue - Interest', 'account_type' => 'Revenue', 'account_class' => 'Non-Operating', 'cost_center_id' => null],
            ['account_code' => '5400', 'account_name' => 'Revenue - Other', 'account_type' => 'Revenue', 'account_class' => 'Non-Operating', 'cost_center_id' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createExpenseAccounts(): void
    {
        $opsCc = CostCenter::where('code', 'OPS-001')->first();
        $techCc = CostCenter::where('code', 'TECH-001')->first();
        $hrCc = CostCenter::where('code', 'HR-001')->first();

        $accounts = [
            // Direct costs
            ['account_code' => '6000', 'account_name' => 'Cost of Goods Sold - Currency', 'account_type' => 'Expense', 'account_class' => 'Direct', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '6010', 'account_name' => 'Expense - Forex Loss', 'account_type' => 'Expense', 'account_class' => 'Direct', 'cost_center_id' => $opsCc?->id],

            // Operating expenses
            ['account_code' => '6100', 'account_name' => 'Expense - Revaluation Loss', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => null],
            ['account_code' => '6200', 'account_name' => 'Expense - Salaries', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $hrCc?->id],
            ['account_code' => '6210', 'account_name' => 'Expense - EPF Employer', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $hrCc?->id],
            ['account_code' => '6220', 'account_name' => 'Expense - EIS', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $hrCc?->id],
            ['account_code' => '6230', 'account_name' => 'Expense - SOCSO', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $hrCc?->id],
            ['account_code' => '6300', 'account_name' => 'Expense - Rent', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '6310', 'account_name' => 'Expense - Utilities', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '6320', 'account_name' => 'Expense - Maintenance', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $opsCc?->id],
            ['account_code' => '6330', 'account_name' => 'Expense - Insurance', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => null],
            ['account_code' => '6400', 'account_name' => 'Expense - IT Infrastructure', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $techCc?->id],
            ['account_code' => '6410', 'account_name' => 'Expense - Software Licenses', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => $techCc?->id],
            ['account_code' => '6500', 'account_name' => 'Expense - Marketing', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => null],
            ['account_code' => '6510', 'account_name' => 'Expense - Travel', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => null],
            ['account_code' => '6520', 'account_name' => 'Expense - Communication', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => null],
            ['account_code' => '6530', 'account_name' => 'Expense - Office Supplies', 'account_type' => 'Expense', 'account_class' => 'Operating', 'cost_center_id' => null],

            // Financial expenses
            ['account_code' => '7000', 'account_name' => 'Expense - Bank Charges', 'account_type' => 'Expense', 'account_class' => 'Financial', 'cost_center_id' => null],
            ['account_code' => '7010', 'account_name' => 'Expense - Interest', 'account_type' => 'Expense', 'account_class' => 'Financial', 'cost_center_id' => null],
            ['account_code' => '7020', 'account_name' => 'Expense - Professional Fees', 'account_type' => 'Expense', 'account_class' => 'Financial', 'cost_center_id' => null],
            ['account_code' => '7030', 'account_name' => 'Expense - Audit Fees', 'account_type' => 'Expense', 'account_class' => 'Financial', 'cost_center_id' => null],
            ['account_code' => '7040', 'account_name' => 'Expense - Regulatory Fees', 'account_type' => 'Expense', 'account_class' => 'Financial', 'cost_center_id' => null],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    private function createIncomeSummaryAccounts(): void
    {
        $finCc = CostCenter::where('code', 'FIN-001')->first();

        $accounts = [
            ['account_code' => '4998', 'account_name' => 'Income Summary', 'account_type' => 'Equity', 'account_class' => 'Income Summary', 'cost_center_id' => $finCc?->id],
            ['account_code' => '4999', 'account_name' => 'Retained Earnings - Current Year', 'account_type' => 'Equity', 'account_class' => 'Retained', 'cost_center_id' => $finCc?->id],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }
}
