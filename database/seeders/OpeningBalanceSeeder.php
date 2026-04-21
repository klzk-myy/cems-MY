<?php

namespace Database\Seeders;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OpeningBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating opening balance journal entries...');

        $fiscalYear = FiscalYear::where('status', 'open')->first();
        if (! $fiscalYear) {
            $this->command->warn('No open fiscal year found. Skipping opening balances.');

            return;
        }

        $period = AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)
            ->where('status', 'open')
            ->first();

        if (! $period) {
            $this->command->warn('No open accounting period found. Skipping opening balances.');

            return;
        }

        $adminUser = User::where('role', 'admin')->first();
        if (! $adminUser) {
            $this->command->warn('No admin user found. Skipping opening balances.');

            return;
        }

        $openingDate = $fiscalYear->start_date;
        $entryNumber = 'OB-'.$fiscalYear->year_code.'-0001';

        DB::transaction(function () use ($fiscalYear, $period, $adminUser, $openingDate, $entryNumber) {
            $journalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'fiscal_year_id' => $fiscalYear->id,
                'period_id' => $period->id,
                'entry_date' => $openingDate,
                'reference_type' => 'Opening Balance',
                'reference_id' => null,
                'description' => 'Initial opening balances - Business commencement',
                'total_amount' => '500000.00',
                'status' => JournalEntryStatus::Posted,
                'created_by' => $adminUser->id,
                'posted_by' => $adminUser->id,
                'posted_at' => now(),
            ]);

            $openingBalances = [
                ['account_code' => '1000', 'debit' => '100000.00', 'credit' => '0.00'],
                ['account_code' => '1010', 'debit' => '50000.00', 'credit' => '0.00'],
                ['account_code' => '1011', 'debit' => '40000.00', 'credit' => '0.00'],
                ['account_code' => '1013', 'debit' => '35000.00', 'credit' => '0.00'],
                ['account_code' => '1300', 'debit' => '150000.00', 'credit' => '0.00'],
                ['account_code' => '1400', 'debit' => '25000.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '400000.00'],
                ['account_code' => '4000', 'debit' => '0.00', 'credit' => '100000.00'],
            ];

            $totalDebits = 0;
            $totalCredits = 0;

            foreach ($openingBalances as $balance) {
                $account = ChartOfAccount::where('account_code', $balance['account_code'])->first();

                if (! $account) {
                    $this->command->warn("Account {$balance['account_code']} not found");

                    continue;
                }

                JournalLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $account->id,
                    'debit_amount' => $balance['debit'],
                    'credit_amount' => $balance['credit'],
                    'description' => 'Opening balance',
                ]);

                $totalDebits += (float) $balance['debit'];
                $totalCredits += (float) $balance['credit'];

                $this->command->info("Created journal line: {$account->account_name} - Dr: {$balance['debit']}, Cr: {$balance['credit']}");
            }

            $this->command->info("Opening balance journal entry created: {$entryNumber}");
            $this->command->info("Total Debits: {$totalDebits}, Total Credits: {$totalCredits}");

            if ($totalDebits !== $totalCredits) {
                $this->command->error('WARNING: Debits and credits do not balance!');
            }
        });

        $this->command->info('Opening balance seeding completed');
    }
}
