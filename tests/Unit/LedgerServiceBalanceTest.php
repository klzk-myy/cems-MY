<?php

namespace Tests\Unit;

use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerServiceBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function get_account_balances_for_period_uses_efficient_queries()
    {
        // Create a user for journal entry posted_by requirement
        $user = User::factory()->create();

        // Create a journal entry (required for account_ledger foreign key)
        $journalEntry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'reference_type' => 'Test',
            'reference_id' => null,
            'description' => 'Test journal entry for ledger balances',
            'status' => 'Posted',
            'posted_by' => $user->id,
        ]);

        // Create 30 active accounts
        $accounts = ChartOfAccount::factory()->count(30)->create(['is_active' => true]);

        $startDate = now()->subDays(30)->toDateString();
        $endDate = now()->toDateString();

        // For each account, create 1-3 ledger entries within the date range
        foreach ($accounts as $account) {
            $numEntries = rand(1, 3);
            for ($i = 0; $i < $numEntries; $i++) {
                AccountLedger::create([
                    'account_code' => $account->account_code,
                    'entry_date' => Carbon::parse($startDate)->addDays(rand(0, 30))->format('Y-m-d'),
                    'journal_entry_id' => $journalEntry->id,
                    'branch_id' => null,
                    'debit' => rand(100, 10000) / 100, // e.g., 1.00 to 100.00
                    'credit' => 0,
                    'running_balance' => rand(100, 10000) / 100,
                ]);
            }
        }

        $ledgerService = app(LedgerService::class);

        DB::enableQueryLog();

        $result = $ledgerService->getAccountBalancesForPeriod(
            $startDate,
            $endDate,
            null // branchId
        );

        $queries = DB::getQueryLog();

        // Should use 2-3 queries (cache check + aggregated query), not 80-150
        $this->assertLessThan(
            5,
            count($queries),
            sprintf('Expected < 5 queries but got %d', count($queries))
        );

        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_debit', $result);
        $this->assertArrayHasKey('total_credit', $result);
        $this->assertArrayHasKey('accounts', $result);
        $this->assertCount(30, $result['accounts']);

        // Verify that all accounts are present
        $returnedCodes = collect($result['accounts'])->pluck('account_code')->sort()->values()->all();
        $expectedCodes = $accounts->pluck('account_code')->sort()->values()->all();
        $this->assertEquals($expectedCodes, $returnedCodes);
    }
}
