<?php

namespace Tests\Unit;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerServiceBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;

    protected Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();
        // Create chart of accounts (Asset, Liability, Equity, Revenue, Expense types)
        $accountTypes = [
            ['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '2000', 'account_name' => 'Payables', 'account_type' => 'Liability', 'is_active' => true],
            ['account_code' => '3000', 'account_name' => 'Capital', 'account_type' => 'Equity', 'is_active' => true],
            ['account_code' => '4000', 'account_name' => 'Sales', 'account_type' => 'Revenue', 'is_active' => true],
            ['account_code' => '5000', 'account_name' => 'Rent Expense', 'account_type' => 'Expense', 'is_active' => true],
        ];
        foreach ($accountTypes as $type) {
            ChartOfAccount::updateOrCreate(['account_code' => $type['account_code']], $type);
        }

        // Create test branches for ledger entry FK constraints
        $this->branch1 = $this->createTestBranch(['code' => 'BR1', 'name' => 'Branch 1']);
        $this->branch2 = $this->createTestBranch(['code' => 'BR2', 'name' => 'Branch 2']);
    }

    #[Test]
    public function get_trial_balance_uses_efficient_queries()
    {
        $ledgerService = $this->app->make(LedgerService::class);

        DB::enableQueryLog();

        $result = $ledgerService->getTrialBalance(now()->toDateString());

        $queries = DB::getQueryLog();

        // Should use 2-3 queries, not 80-150
        $this->assertLessThan(
            5,
            count($queries),
            sprintf('Expected < 5 queries but got %d', count($queries))
        );

        // Verify structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('accounts', $result);
        $this->assertArrayHasKey('total_debits', $result);
        $this->assertArrayHasKey('total_credits', $result);
        $this->assertArrayHasKey('is_balanced', $result);
        $this->assertArrayHasKey('as_of_date', $result);

        // Verify we have accounts (some may be from default data)
        $this->assertNotEmpty($result['accounts']);
    }

    #[Test]
    public function trial_balance_filters_by_branch(): void
    {
        // Clear cached trial balance data from previous test to ensure fresh query
        Cache::tags(['ledger', 'trial-balance'])->flush();

        $ledgerService = $this->app->make(LedgerService::class);

        // Create branch-specific ledger entries
        $cashAccount = ChartOfAccount::where('account_code', '1000')->first();
        $today = now()->toDateString();

        // Create an accounting period first (required for journal_entries FK)
        $period = AccountingPeriod::firstOrCreate(
            ['period_code' => now()->format('Y-m')],
            [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'status' => 'Open',
            ]
        );

        // Create a journal entry using factory (required for foreign key)
        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => $today,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        // Create ledger entries for branch 1
        DB::table('account_ledger')->insert([
            'account_code' => '1000',
            'branch_id' => $this->branch1->id,
            'entry_date' => $today,
            'journal_entry_id' => $journalEntry->id,
            'running_balance' => '1000.00',
            'debit' => '1000.00',
            'credit' => '0.00',
            'created_at' => now(),
        ]);

        // Create another journal entry for branch 2
        $journalEntry2 = JournalEntry::factory()->create([
            'entry_date' => $today,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        // Create ledger entries for branch 2
        DB::table('account_ledger')->insert([
            'account_code' => '1000',
            'branch_id' => $this->branch2->id,
            'entry_date' => $today,
            'journal_entry_id' => $journalEntry2->id,
            'running_balance' => '2000.00',
            'debit' => '2000.00',
            'credit' => '0.00',
            'created_at' => now(),
        ]);

        // Get trial balance for all branches (should aggregate both)
        $allBranches = $ledgerService->getTrialBalance($today);
        $allBranchesCash = collect($allBranches['accounts'])->firstWhere('account_code', '1000');

        // Get trial balance for branch 1 only
        $branch1 = $ledgerService->getTrialBalance($today, $this->branch1->id);
        $branch1Cash = collect($branch1['accounts'])->firstWhere('account_code', '1000');

        // Get trial balance for branch 2 only
        $branch2 = $ledgerService->getTrialBalance($today, $this->branch2->id);
        $branch2Cash = collect($branch2['accounts'])->firstWhere('account_code', '1000');

        // Verify consolidated balance shows combined amount
        $this->assertEquals('3000.0000', $allBranchesCash['balance']);

        // Verify branch 1 shows only its own balance
        $this->assertEquals('1000.0000', $branch1Cash['balance']);

        // Verify branch 2 shows only its own balance
        $this->assertEquals('2000.0000', $branch2Cash['balance']);
    }

    #[Test]
    public function trial_balance_places_credit_normal_balance_in_credit_column(): void
    {
        Cache::tags(['ledger', 'trial-balance'])->flush();

        $ledgerService = $this->app->make(LedgerService::class);
        $today = now()->toDateString();

        $period = AccountingPeriod::firstOrCreate(
            ['period_code' => now()->format('Y-m')],
            [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'status' => 'Open',
            ]
        );

        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => $today,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        // Cash debit 36 / Sales credit 36 — balanced books. A credit-normal
        // balance must render in the credit column; the previous sign logic
        // put it in the debit column and reported is_balanced=false.
        DB::table('account_ledger')->insert([
            'account_code' => '1000',
            'branch_id' => $this->branch1->id,
            'entry_date' => $today,
            'journal_entry_id' => $journalEntry->id,
            'running_balance' => '36.0000',
            'debit' => '36.00',
            'credit' => '0.00',
            'created_at' => now(),
        ]);

        DB::table('account_ledger')->insert([
            'account_code' => '4000',
            'branch_id' => $this->branch1->id,
            'entry_date' => $today,
            'journal_entry_id' => $journalEntry->id,
            'running_balance' => '-36.0000',
            'debit' => '0.00',
            'credit' => '36.00',
            'created_at' => now(),
        ]);

        $result = $ledgerService->getTrialBalance($today);
        $sales = collect($result['accounts'])->firstWhere('account_code', '4000');

        $this->assertEquals('0', $sales['debit']);
        $this->assertEquals('36.0000', $sales['credit']);
        $this->assertEquals('36.0000', $result['total_debits']);
        $this->assertEquals('36.0000', $result['total_credits']);
        $this->assertTrue($result['is_balanced']);
    }
}
