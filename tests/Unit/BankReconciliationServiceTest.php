<?php

namespace Tests\Unit;

use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BankReconciliationService $bankReconciliationService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bankReconciliationService = new BankReconciliationService;
        $this->user = User::create([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    public function test_auto_match_matches_debit_statement_to_journal_entry(): void
    {
        $accountCode = '1001';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        $chartOfAccount = ChartOfAccount::create([
            'account_code' => $accountCode,
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'Posted',
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

        $this->assertEquals('matched', $reconciliation->status);
        $this->assertEquals($journalEntry->id, $reconciliation->matched_to_journal_entry_id);
        $this->assertNotNull($reconciliation->matched_at);
    }

    public function test_auto_match_matches_credit_statement_to_journal_entry(): void
    {
        $accountCode = '1002';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        $chartOfAccount = ChartOfAccount::create([
            'account_code' => $accountCode,
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'Posted',
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

        $this->assertEquals('matched', $reconciliation->status);
        $this->assertEquals($journalEntry->id, $reconciliation->matched_to_journal_entry_id);
        $this->assertNotNull($reconciliation->matched_at);
    }

    public function test_auto_match_skips_checks(): void
    {
        $accountCode = '1003';
        $statementDate = now()->toDateString();
        $amount = '1000.00';

        $chartOfAccount = ChartOfAccount::create([
            'account_code' => $accountCode,
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'Posted',
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

        $this->assertEquals('unmatched', $reconciliation->status);
        $this->assertNull($reconciliation->matched_to_journal_entry_id);
    }

    public function test_auto_match_does_not_match_different_amounts(): void
    {
        $accountCode = '1004';
        $statementDate = now()->toDateString();

        $chartOfAccount = ChartOfAccount::create([
            'account_code' => $accountCode,
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::create([
            'entry_date' => $statementDate,
            'description' => 'Test entry',
            'status' => 'Posted',
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

        $this->assertEquals('unmatched', $reconciliation->status);
        $this->assertNull($reconciliation->matched_to_journal_entry_id);
    }

    public function test_auto_match_does_not_match_different_dates(): void
    {
        $accountCode = '1005';
        $amount = '1000.00';

        $chartOfAccount = ChartOfAccount::create([
            'account_code' => $accountCode,
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::create([
            'entry_date' => now()->subDay()->toDateString(),
            'description' => 'Test entry',
            'status' => 'Posted',
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

        $this->assertEquals('unmatched', $reconciliation->status);
        $this->assertNull($reconciliation->matched_to_journal_entry_id);
    }
}
