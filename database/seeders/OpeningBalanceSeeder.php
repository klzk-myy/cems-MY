<?php

namespace Database\Seeders;

use App\Enums\AccountCode;
use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountingPeriodType;
use App\Enums\FiscalYearStatus;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Database\Seeder;

/**
 * Seeds a single balanced opening-balance journal entry.
 *
 * Debit Cash MYR 500,000 / Credit Capital Paid-In 500,000, posted through
 * AccountingService so balance validation and ledger rows are handled by the
 * canonical posting path. The entry is only booked when an open fiscal year
 * and an admin user exist, and it is never duplicated on rerun.
 */
class OpeningBalanceSeeder extends Seeder
{
    protected const OPENING_AMOUNT = '500000.00';

    public function __construct(protected AccountingService $accountingService) {}

    public function run(): void
    {
        $this->command->info('Creating opening balance journal entry...');

        if (JournalEntry::where('reference_type', 'Opening Balance')->exists()) {
            $this->command->info('Opening balance entry already exists. Skipping.');

            return;
        }

        $fiscalYear = FiscalYear::where('status', FiscalYearStatus::Open->value)
            ->orderBy('start_date')
            ->first();

        if (! $fiscalYear) {
            $this->command->warn('No open fiscal year found. Skipping opening balances.');

            return;
        }

        $adminUser = User::where('role', UserRole::Admin->value)->first();

        if (! $adminUser) {
            $this->command->warn('No admin user found. Skipping opening balances.');

            return;
        }

        // Ensure an open accounting period exists for the fiscal year start
        // date — AccountingService::createJournalEntry() requires every entry
        // to be linked to an open AccountingPeriod (spec.md §4.1).
        $periodDate = $fiscalYear->start_date;
        AccountingPeriod::firstOrCreate(
            ['period_code' => $periodDate->format('Y-m')],
            [
                'fiscal_year_id' => $fiscalYear->id,
                'start_date' => $periodDate->copy()->startOfMonth()->toDateString(),
                'end_date' => $periodDate->copy()->endOfMonth()->toDateString(),
                'period_type' => AccountingPeriodType::Month->value,
                'status' => AccountingPeriodStatus::Open->value,
            ]
        );

        $entry = $this->accountingService->createJournalEntry(
            lines: [
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => self::OPENING_AMOUNT,
                    'credit' => '0',
                    'description' => 'Opening balance - Cash (MYR)',
                ],
                [
                    'account_code' => AccountCode::CAPITAL_PAID_IN->value,
                    'debit' => '0',
                    'credit' => self::OPENING_AMOUNT,
                    'description' => 'Opening balance - Capital Paid-In',
                ],
            ],
            referenceType: 'Opening Balance',
            description: 'Opening Balance - Business commencement',
            entryDate: $fiscalYear->start_date->toDateString(),
            createdBy: $adminUser->id,
        );

        $this->command->info("Opening balance journal entry created: {$entry->id}");
        $this->command->info('Opening balance seeding completed');
    }
}
