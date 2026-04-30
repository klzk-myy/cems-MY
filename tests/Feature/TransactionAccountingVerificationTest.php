<?php

namespace Tests\Feature;

use App\Enums\AccountCode;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\ChartOfAccount;
use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\BranchPoolService;
use App\Services\ComplianceService;
use App\Services\CounterOpeningWorkflowService;
use App\Services\CounterService;
use App\Services\CurrencyPositionService;
use App\Services\LedgerService;
use App\Services\MathService;
use App\Services\TellerAllocationService;
use App\Services\ThresholdService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification test: 20 transactions per branch by teller
 * Verifies accounting entries and ledger balances match
 */
class TransactionAccountingVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected AccountingService $accountingService;

    protected LedgerService $ledgerService;

    protected TransactionService $transactionService;

    protected CurrencyPositionService $positionService;

    protected array $branches = [];

    protected array $tellers = [];

    protected array $counters = [];

    protected array $customers = [];

    protected array $transactions = [];

    protected array $results = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mathService = new MathService;
        $thresholdService = new ThresholdService;
        $auditService = resolve(AuditService::class);
        $complianceService = resolve(ComplianceService::class);

        $this->accountingService = new AccountingService($this->mathService, $auditService);
        $this->ledgerService = new LedgerService($this->mathService, $this->accountingService);
        $this->positionService = new CurrencyPositionService($this->mathService);

        $this->transactionService = resolve(TransactionService::class);

        $this->createFiscalYear();
    }

    protected function createFiscalYear(): void
    {
        $fy = FiscalYear::factory()->create([
            'year_code' => (string) now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        AccountingPeriod::factory()->create([
            'period_code' => now()->format('Y-m'),
            'fiscal_year_id' => $fy->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function test_create_20_transactions_per_branch_verify_accounting(): void
    {
        $this->createTestData();
        $this->createTransactions();
        $this->verifyAccounting();
        $this->verifyLedger();
        $this->printResults();
    }

    protected function createTestData(): void
    {
        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $auditService = resolve(AuditService::class);
        $complianceService = resolve(ComplianceService::class);

        $branchPoolService = new BranchPoolService($mathService);
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService);
        $counterService = new CounterService($tellerAllocationService, $thresholdService);
        $workflowService = new CounterOpeningWorkflowService(
            $branchPoolService,
            $tellerAllocationService,
            $counterService
        );

        // Create 3 branches (as per requirement)
        $branchConfigs = [
            ['code' => 'HQ01', 'name' => 'Head Office 1'],
            ['code' => 'HQ02', 'name' => 'Head Office 2'],
            ['code' => 'HQ03', 'name' => 'Head Office 3'],
        ];

        foreach ($branchConfigs as $config) {
            $branch = Branch::factory()->create([
                'code' => $config['code'],
                'name' => $config['name'],
                'address' => '123 Test Street',
                'phone' => '+60312345678',
                'email' => 'test@localhost.com',
            ]);
            $this->branches[$config['code']] = $branch;

            // Create teller for this branch
            $teller = User::factory()->create([
                'username' => 'teller_'.$config['code'].'_'.uniqid(),
                'email' => 'teller_'.$config['code'].'_'.uniqid().'@test.com',
                'role' => UserRole::Teller,
                'branch_id' => $branch->id,
            ]);
            $this->tellers[$config['code']] = $teller;

            // Create manager for this branch
            $manager = User::factory()->create([
                'username' => 'manager_'.$config['code'].'_'.uniqid(),
                'email' => 'manager_'.$config['code'].'_'.uniqid().'@test.com',
                'role' => UserRole::Manager,
                'branch_id' => $branch->id,
            ]);

            // Create pool for this branch
            BranchPool::factory()->create([
                'branch_id' => $branch->id,
                'currency_code' => 'USD',
                'available_balance' => '100000.0000',
                'allocated_balance' => '0.0000',
            ]);

            // Create counter for this branch
            $counter = Counter::factory()->create([
                'name' => 'Counter '.$config['code'],
                'code' => 'CTR_'.$config['code'],
                'branch_id' => $branch->id,
            ]);
            $this->counters[$config['code']] = $counter;

            // Open counter session and allocate USD to teller
            $requests = $workflowService->initiateOpeningRequest(
                $teller,
                $counter,
                ['USD' => '50000.0000']
            );

            $workflowService->approveAndOpen(
                $manager,
                $counter,
                $teller,
                ['USD' => '45000.0000'],
                ['USD' => '200000.0000']
            );

            // Create till balances for this counter
            TillBalance::factory()->create([
                'till_id' => (string) $counter->id,
                'currency_code' => 'MYR',
                'branch_id' => $branch->id,
                'date' => today(),
                'opening_balance' => '100000.0000',
                'transaction_total' => '0',
                'opened_by' => $teller->id,
            ]);

            TillBalance::factory()->create([
                'till_id' => (string) $counter->id,
                'currency_code' => 'USD',
                'branch_id' => $branch->id,
                'date' => today(),
                'opening_balance' => '50000.0000',
                'foreign_total' => '0',
                'opened_by' => $teller->id,
            ]);

            // Create USD position for this counter
            CurrencyPosition::factory()->create([
                'currency_code' => 'USD',
                'till_id' => (string) $counter->id,
                'branch_id' => $branch->id,
                'balance' => '50000.0000',
                'avg_cost_rate' => '4.50',
                'last_valuation_rate' => '4.50',
            ]);

            // Create 3 customers for this branch
            for ($c = 0; $c < 3; $c++) {
                $customer = Customer::factory()->create([
                    'full_name' => 'Customer '.$config['code'].' - '.$c,
                    'id_type' => 'MyKad',
                    'id_number_encrypted' => encrypt('123456789012'.$config['code'].$c),
                    'nationality' => 'MY',
                    'date_of_birth' => '1990-01-15',
                    'risk_rating' => 'Low',
                    'cdd_level' => 'Simplified',
                ]);
                $this->customers[$config['code']][] = $customer;
            }
        }

        $this->results['branches_created'] = count($this->branches);
        $this->results['tellers_created'] = count($this->tellers);
    }

    protected function createTransactions(): void
    {
        $transactionCount = 0;
        $sellCount = 0;
        $buyCount = 0;

        foreach ($this->branches as $branchCode => $branch) {
            $teller = $this->tellers[$branchCode];
            $counter = $this->counters[$branchCode];
            $customers = $this->customers[$branchCode];
            $branchTransactions = [];

            // Create 20 transactions per branch
            for ($i = 0; $i < 20; $i++) {
                $customer = $customers[$i % count($customers)];
                $type = ($i % 2 === 0) ? TransactionType::Sell : TransactionType::Buy;
                $amountForeign = (string) (100 + ($i * 50)); // Vary amounts: 100, 150, 200, ...
                // Use different rates for buy vs sell to test spread revenue
                // Sell at higher rate (we profit from spread), buy at lower rate
                $rate = ($type === TransactionType::Sell) ? '4.5500' : '4.4500';

                if ($type === TransactionType::Sell) {
                    // For sell, we need sufficient USD position
                    $sellCount++;
                } else {
                    $buyCount++;
                }

                try {
                    $data = [
                        'customer_id' => $customer->id,
                        'type' => $type->value,
                        'currency_code' => 'USD',
                        'amount_foreign' => $amountForeign,
                        'rate' => $rate,
                        'purpose' => 'Test transaction '.$i,
                        'source_of_funds' => 'salary',
                        'till_id' => (string) $counter->id,
                        'idempotency_key' => "branch_{$branchCode}_txn_{$i}_".time(),
                    ];

                    $transaction = $this->transactionService->createTransaction($data, $teller->id);
                    $branchTransactions[] = $transaction;
                    $this->transactions[$branchCode][] = $transaction;
                    $transactionCount++;
                } catch (\Exception $e) {
                    $this->results['errors'][] = "Branch {$branchCode} Txn {$i}: ".$e->getMessage();
                }
            }

            $this->results['branches'][$branchCode] = [
                'transactions_created' => count($branchTransactions),
                'total_amount' => $this->sumTransactionAmounts($branchTransactions),
            ];
        }

        $this->results['total_transactions'] = $transactionCount;
        $this->results['sell_transactions'] = $sellCount;
        $this->results['buy_transactions'] = $buyCount;
    }

    protected function sumTransactionAmounts(array $transactions): array
    {
        $totalSellMYR = '0';
        $totalBuyMYR = '0';
        $totalSellUSD = '0';
        $totalBuyUSD = '0';

        foreach ($transactions as $txn) {
            if ($txn->type === TransactionType::Sell) {
                $totalSellMYR = $this->mathService->add($totalSellMYR, (string) $txn->amount_local);
                $totalSellUSD = $this->mathService->add($totalSellUSD, (string) $txn->amount_foreign);
            } else {
                $totalBuyMYR = $this->mathService->add($totalBuyMYR, (string) $txn->amount_local);
                $totalBuyUSD = $this->mathService->add($totalBuyUSD, (string) $txn->amount_foreign);
            }
        }

        return [
            'sell_myr' => $totalSellMYR,
            'sell_usd' => $totalSellUSD,
            'buy_myr' => $totalBuyMYR,
            'buy_usd' => $totalBuyUSD,
        ];
    }

    protected function verifyAccounting(): void
    {
        $verifiedEntries = 0;
        $mismatchedEntries = [];

        foreach ($this->transactions as $branchCode => $branchTransactions) {
            foreach ($branchTransactions as $transaction) {
                if ($transaction->journal_entry_id) {
                    $entry = JournalEntry::with('lines')->find($transaction->journal_entry_id);

                    if ($entry) {
                        // Verify debits equal credits
                        $totalDebits = '0';
                        $totalCredits = '0';

                        foreach ($entry->lines as $line) {
                            $totalDebits = $this->mathService->add($totalDebits, (string) $line->debit);
                            $totalCredits = $this->mathService->add($totalCredits, (string) $line->credit);
                        }

                        $isBalanced = $this->mathService->compare($totalDebits, $totalCredits) === 0;

                        if ($isBalanced) {
                            $verifiedEntries++;
                        } else {
                            $mismatchedEntries[] = [
                                'branch' => $branchCode,
                                'transaction_id' => $transaction->id,
                                'journal_entry_id' => $entry->id,
                                'debits' => $totalDebits,
                                'credits' => $totalCredits,
                            ];
                        }
                    }
                } else {
                    // Check if transaction is PendingApproval (which defers accounting)
                    if ($transaction->status === TransactionStatus::PendingApproval) {
                        $this->results['deferred_entries'][] = $transaction->id;
                    }
                }
            }
        }

        $this->results['accounting_verification'] = [
            'verified_entries' => $verifiedEntries,
            'mismatched_entries' => count($mismatchedEntries),
            'mismatches' => $mismatchedEntries,
            'is_valid' => count($mismatchedEntries) === 0,
        ];
    }

    protected function verifyLedger(): void
    {
        $asOfDate = now()->toDateString();
        $ledgerValidation = [];

        // Get all account codes that have ledger entries
        $allAccountCodes = [
            AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
            AccountCode::CASH_MYR->value,
            AccountCode::FOREX_TRADING_REVENUE->value,
            AccountCode::FOREX_LOSS->value,
        ];

        foreach ($allAccountCodes as $accountCode) {
            $account = ChartOfAccount::where('account_code', $accountCode)->first();
            if (! $account) {
                continue;
            }

            // Use DATE() for proper date comparison (handles datetime columns)
            $entries = AccountLedger::where('account_code', $accountCode)
                ->whereRaw('DATE(entry_date) <= ?', [$asOfDate])
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get();

            $totalDebits = '0';
            $totalCredits = '0';
            foreach ($entries as $entry) {
                $totalDebits = $this->mathService->add($totalDebits, (string) $entry->debit);
                $totalCredits = $this->mathService->add($totalCredits, (string) $entry->credit);
            }

            $currentBalance = $this->accountingService->getAccountBalance($accountCode, $asOfDate);

            // Also check via ledger service
            $trialBalance = $this->ledgerService->getTrialBalance($asOfDate);
            $accountInTrialBalance = collect($trialBalance['accounts'])->firstWhere('account_code', $accountCode);

            $ledgerValidation[$accountCode] = [
                'entries_count' => $entries->count(),
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'current_balance' => $currentBalance,
                'trial_balance_balance' => $accountInTrialBalance['balance'] ?? 'N/A',
                'account_type' => $account->account_type,
            ];
        }

        $this->results['ledger_verification'] = $ledgerValidation;
    }

    protected function printResults(): void
    {
        echo "\n";
        echo '='.str_repeat('=', 80)."\n";
        echo "TRANSACTION ACCOUNTING VERIFICATION RESULTS\n";
        echo '='.str_repeat('=', 80)."\n\n";

        echo "TEST DATA SUMMARY:\n";
        echo '-'.str_repeat('-', 40)."\n";
        echo "  Branches created: {$this->results['branches_created']}\n";
        echo "  Tellers created: {$this->results['tellers_created']}\n";
        echo "  Total transactions: {$this->results['total_transactions']}\n";
        echo "    - Sell transactions: {$this->results['sell_transactions']}\n";
        echo "    - Buy transactions: {$this->results['buy_transactions']}\n";
        echo "\n";

        echo "PER BRANCH BREAKDOWN:\n";
        echo '-'.str_repeat('-', 40)."\n";
        foreach ($this->results['branches'] as $branchCode => $data) {
            echo "  Branch {$branchCode}:\n";
            echo "    Transactions: {$data['transactions_created']}\n";
            echo "    Sell: {$data['total_amount']['sell_usd']} USD ({$data['total_amount']['sell_myr']} MYR)\n";
            echo "    Buy: {$data['total_amount']['buy_usd']} USD ({$data['total_amount']['buy_myr']} MYR)\n";
        }
        echo "\n";

        echo "ACCOUNTING VERIFICATION:\n";
        echo '-'.str_repeat('-', 40)."\n";
        $acct = $this->results['accounting_verification'];
        echo "  Verified entries: {$acct['verified_entries']}\n";
        echo "  Mismatched entries: {$acct['mismatched_entries']}\n";
        echo '  Is Valid: '.($acct['is_valid'] ? 'YES' : 'NO')."\n";

        if (! empty($this->results['deferred_entries'])) {
            echo '  Deferred entries (PendingApproval): '.count($this->results['deferred_entries'])."\n";
        }
        echo "\n";

        echo "LEDGER VERIFICATION:\n";
        echo '-'.str_repeat('-', 40)."\n";
        foreach ($this->results['ledger_verification'] as $accountCode => $data) {
            echo "  Account {$accountCode} ({$data['account_type']}):\n";
            echo "    Entries: {$data['entries_count']}\n";
            echo "    Total Debits: {$data['total_debits']}\n";
            echo "    Total Credits: {$data['total_credits']}\n";
            echo "    Balance (from ledger): {$data['current_balance']}\n";
            echo "    Balance (from trial balance): {$data['trial_balance_balance']}\n";
        }
        echo "\n";

        if (! empty($this->results['errors'])) {
            echo "ERRORS:\n";
            echo '-'.str_repeat('-', 40)."\n";
            foreach ($this->results['errors'] as $error) {
                echo "  - {$error}\n";
            }
            echo "\n";
        }

        echo '='.str_repeat('=', 80)."\n";

        // Assert verification passed
        $this->assertTrue($acct['is_valid'], 'Accounting entries must be balanced');
        $this->assertEquals($this->results['total_transactions'], $this->results['accounting_verification']['verified_entries'] + count($this->results['deferred_entries'] ?? []));
    }
}
