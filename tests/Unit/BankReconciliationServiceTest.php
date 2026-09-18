<?php

namespace Tests\Unit;

use App\Enums\BankReconciliationStatus;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\Accounting\BankReconciliationService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BankReconciliationService $bankReconciliationService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bankReconciliationService = new BankReconciliationService(new MathService);
        $this->user = User::factory()->create([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    #[Test]
    public function auto_match_matches_debit_statement_to_journal_entry(): void
    {
        $accountCode = '1001';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        // updateOrCreate: 1001 (CASH_USD) is seeded from the AccountCode
        // enum by SchemaSeeder.
        $chartOfAccount = ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'reference_type' => 'Manual',
        ]);

        JournalLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_code' => $accountCode,
            'debit' => $amount,
            'credit' => '0.00',
            'description' => 'Test line',
        ]);

        $reconciliation = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $statementDate,
            'description' => 'Test statement',
            'debit' => $amount,
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $this->bankReconciliationService->autoMatch($accountCode);

        $reconciliation->refresh();

        $this->assertEquals(BankReconciliationStatus::Matched, $reconciliation->status);
        $this->assertEquals($journalEntry->id, $reconciliation->matched_to_journal_entry_id);
        $this->assertNotNull($reconciliation->matched_at);
    }

    #[Test]
    public function auto_match_matches_credit_statement_to_journal_entry(): void
    {
        $accountCode = '1002';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        // updateOrCreate: 1001 (CASH_USD) is seeded from the AccountCode
        // enum by SchemaSeeder.
        $chartOfAccount = ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'reference_type' => 'Manual',
        ]);

        JournalLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_code' => $accountCode,
            'debit' => '0.00',
            'credit' => $amount,
            'description' => 'Test line',
        ]);

        $reconciliation = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $statementDate,
            'description' => 'Test statement',
            'debit' => '0.00',
            'credit' => $amount,
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $this->bankReconciliationService->autoMatch($accountCode);

        $reconciliation->refresh();

        $this->assertEquals(BankReconciliationStatus::Matched, $reconciliation->status);
        $this->assertEquals($journalEntry->id, $reconciliation->matched_to_journal_entry_id);
        $this->assertNotNull($reconciliation->matched_at);
    }

    #[Test]
    public function auto_match_skips_checks(): void
    {
        $accountCode = '1003';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        // updateOrCreate: 1001 (CASH_USD) is seeded from the AccountCode
        // enum by SchemaSeeder.
        $chartOfAccount = ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'reference_type' => 'Manual',
        ]);

        JournalLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_code' => $accountCode,
            'debit' => $amount,
            'credit' => '0.00',
            'description' => 'Test line',
        ]);

        $reconciliation = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $statementDate,
            'description' => 'Test statement',
            'debit' => $amount,
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
            'check_number' => '12345',
        ]);

        $this->bankReconciliationService->autoMatch($accountCode);

        $reconciliation->refresh();

        $this->assertEquals(BankReconciliationStatus::Unmatched, $reconciliation->status);
        $this->assertNull($reconciliation->matched_to_journal_entry_id);
    }

    #[Test]
    public function auto_match_does_not_match_different_amounts(): void
    {
        $accountCode = '1004';
        $statementDate = now()->toDateString();

        // updateOrCreate: 1001 (CASH_USD) is seeded from the AccountCode
        // enum by SchemaSeeder.
        $chartOfAccount = ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'reference_type' => 'Manual',
        ]);

        JournalLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_code' => $accountCode,
            'debit' => '1000.00',
            'credit' => '0.00',
            'description' => 'Test line',
        ]);

        $reconciliation = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $statementDate,
            'description' => 'Test statement',
            'debit' => '2000.00',
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $this->bankReconciliationService->autoMatch($accountCode);

        $reconciliation->refresh();

        $this->assertEquals(BankReconciliationStatus::Unmatched, $reconciliation->status);
        $this->assertNull($reconciliation->matched_to_journal_entry_id);
    }

    #[Test]
    public function auto_match_does_not_match_different_dates(): void
    {
        $accountCode = '1005';
        $amount = '1000.00';

        // updateOrCreate: 1001 (CASH_USD) is seeded from the AccountCode
        // enum by SchemaSeeder.
        $chartOfAccount = ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $journalEntry = JournalEntry::create([
            'entry_date' => now()->subDay()->toDateString(),
            'description' => 'Test entry',
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'reference_type' => 'Manual',
        ]);

        JournalLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_code' => $accountCode,
            'debit' => $amount,
            'credit' => '0.00',
            'description' => 'Test line',
        ]);

        $reconciliation = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => now()->toDateString(),
            'description' => 'Test statement',
            'debit' => $amount,
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $this->bankReconciliationService->autoMatch($accountCode);

        $reconciliation->refresh();

        $this->assertEquals(BankReconciliationStatus::Unmatched, $reconciliation->status);
        $this->assertNull($reconciliation->matched_to_journal_entry_id);
    }

    #[Test]
    public function auto_match_does_not_match_same_journal_entry_twice(): void
    {
        $accountCode = '1006';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'posted',
            'posted_by' => $this->user->id,
            'reference_type' => 'Manual',
        ]);

        JournalLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_code' => $accountCode,
            'debit' => $amount,
            'credit' => '0.00',
            'description' => 'Test line',
        ]);

        // Two identical statement lines but only one journal entry — the
        // second must stay unmatched rather than double-matching.
        $first = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $statementDate,
            'description' => 'Statement line 1',
            'debit' => $amount,
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $second = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $statementDate,
            'description' => 'Statement line 2',
            'debit' => $amount,
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $this->bankReconciliationService->autoMatch($accountCode);

        $first->refresh();
        $second->refresh();

        $this->assertEquals(BankReconciliationStatus::Matched, $first->status);
        $this->assertEquals($journalEntry->id, $first->matched_to_journal_entry_id);

        $this->assertEquals(BankReconciliationStatus::Unmatched, $second->status);
        $this->assertNull($second->matched_to_journal_entry_id);
    }

    #[Test]
    public function report_and_view_data_share_one_normalized_unmatched_item_shape(): void
    {
        $accountCode = '1007';
        $fromDate = now()->startOfMonth()->toDateString();
        $toDate = now()->endOfMonth()->toDateString();

        ChartOfAccount::updateOrCreate(
            ['account_code' => $accountCode],
            [
                'account_name' => 'Cash',
                'account_type' => 'Asset',
                'is_active' => true,
            ]
        );

        $check = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $fromDate,
            'reference' => 'CHK-1',
            'description' => 'Outstanding check',
            'debit' => '500.00',
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $deposit = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $fromDate,
            'reference' => 'DEP-1',
            'description' => 'Deposit in transit',
            'debit' => '0.00',
            'credit' => '250.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        // Matched, exception, and out-of-range lines must not leak into the
        // unmatched set any consumer renders.
        BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $fromDate,
            'description' => 'Matched line',
            'debit' => '10.00',
            'credit' => '0.00',
            'status' => 'matched',
            'created_by' => $this->user->id,
        ]);

        $exception = BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => $fromDate,
            'description' => 'Exception line',
            'debit' => '75.00',
            'credit' => '0.00',
            'status' => 'exception',
            'notes' => 'Needs review',
            'created_by' => $this->user->id,
        ]);

        BankReconciliation::create([
            'account_code' => $accountCode,
            'statement_date' => now()->subMonths(2)->toDateString(),
            'description' => 'Out of range',
            'debit' => '99.00',
            'credit' => '0.00',
            'status' => 'unmatched',
            'created_by' => $this->user->id,
        ]);

        $report = $this->bankReconciliationService->getReconciliationReport($accountCode, $fromDate, $toDate);
        $viewData = $this->bankReconciliationService->getReconciliationViewData($accountCode, $fromDate, $toDate);

        $expectedKeys = ['id', 'date', 'reference', 'description', 'debit', 'credit', 'amount', 'status', 'notes'];

        $unmatchedItems = collect($report['unmatched_items']);
        $exceptions = collect($report['exceptions']);

        $this->assertCount(2, $unmatchedItems);
        $this->assertSame($expectedKeys, array_keys($unmatchedItems->first()));
        $this->assertCount(1, $exceptions);
        $this->assertSame($expectedKeys, array_keys($exceptions->first()));
        $this->assertEquals(BankReconciliationStatus::Unmatched, $unmatchedItems->first()['status']);
        $this->assertEquals(BankReconciliationStatus::Exception, $exceptions->first()['status']);

        // The index lists are a partition of the exact same normalized rows.
        $this->assertEqualsCanonicalizing(
            $unmatchedItems->pluck('id')->all(),
            [
                ...collect($viewData['outstanding_checks_list'])->pluck('id')->all(),
                ...collect($viewData['outstanding_deposits_list'])->pluck('id')->all(),
            ]
        );

        $this->assertSame([$check->id], collect($viewData['outstanding_checks_list'])->pluck('id')->all());
        $this->assertSame([$deposit->id], collect($viewData['outstanding_deposits_list'])->pluck('id')->all());
        $this->assertSame($exception->id, $exceptions->first()['id']);
    }
}
