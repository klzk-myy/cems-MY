<?php

namespace Tests\Feature\Audit;

use App\Enums\StockTransferStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionValidationException;
use App\Jobs\Audit\SealAuditHashJob;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\LedgerService;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\MonitoringEngine;
use App\Services\Compliance\Monitors\BaseMonitor;
use App\Services\Compliance\Monitors\CounterfeitAlertMonitor;
use App\Services\Compliance\Monitors\CurrencyFlowMonitor;
use App\Services\Compliance\Monitors\CustomerLocationAnomalyMonitor;
use App\Services\Compliance\Monitors\SanctionsRescreeningMonitor;
use App\Services\Compliance\Monitors\StructuringMonitor;
use App\Services\Compliance\Monitors\VelocityMonitor;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\FinancialRatioService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for pass-4 fixes:
 * - FinancialRatioService: null value coercion (no ValueError on empty ledger)
 * - LedgerService: bcmath sums instead of float Collection::sum()
 * - AccountingService: branch_id threaded into journal entries + ledger, SQL aggregate activity
 * - ExportService: CSV formula-injection sanitization
 * - StockTransferService: over-receipt guard
 * - CustomerLocationAnomalyMonitor: bcmath sum of amounts
 */
class ReportingAccountingFixesTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
    }

    #[Test]
    public function financial_ratio_returns_zero_for_empty_ledger(): void
    {
        // Revenue + Expense accounts exist, but no ledger rows. Previously
        // value('net') returned null -> (string) '' fed to bcmath -> ValueError.
        ChartOfAccount::updateOrCreate(
            ['account_code' => '8100'],
            ['account_type' => 'Revenue', 'account_name' => 'Sales Revenue', 'is_active' => true]
        );
        ChartOfAccount::updateOrCreate(
            ['account_code' => '8200'],
            ['account_type' => 'Expense', 'account_name' => 'Rent Expense', 'is_active' => true]
        );

        $service = new FinancialRatioService($this->mathService);

        $ratios = $service->getProfitabilityRatios('2026-01-01', '2026-01-31');

        $this->assertSame('0', $ratios['revenue']);
        $this->assertSame('0', $ratios['cogs']);
        $this->assertSame('0.0000', $ratios['net_income']);
        $this->assertSame('0.0000', $ratios['gross_profit']);
    }

    #[Test]
    public function financial_ratio_handles_accounts_without_ledger_rows(): void
    {
        // An Asset account with zero ledger rows must contribute 0 to the
        // totals; previously an empty latest-balance map was not the issue, but
        // missing per-account balances must never feed '' into bcmath.
        ChartOfAccount::updateOrCreate(
            ['account_code' => '8300'],
            ['account_type' => 'Asset', 'account_name' => 'Prepaid', 'is_active' => true]
        );

        $service = new FinancialRatioService($this->mathService);

        $ratios = $service->getLiquidityRatios('2026-01-31');

        $this->assertSame('0', $ratios['current_assets']);
        $this->assertSame('0', $ratios['current_ratio']);
    }

    #[Test]
    public function ledger_account_totals_preserve_decimal_precision(): void
    {
        $branch = Branch::factory()->create(['code' => 'HQ', 'name' => 'Headquarters']);
        ChartOfAccount::updateOrCreate(
            ['account_code' => '8400'],
            ['account_type' => 'Asset', 'account_name' => 'Cash', 'is_active' => true]
        );

        $today = '2026-01-15';
        $period = AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        // 0.10 + 0.20: float sum produces 0.30000000000000004; bcmath -> 0.3000
        foreach (['0.10', '0.20'] as $debit) {
            $journalEntry = JournalEntry::factory()->create([
                'entry_date' => $today,
                'period_id' => $period->id,
                'branch_id' => $branch->id,
            ]);

            DB::table('account_ledger')->insert([
                'account_code' => '8400',
                'branch_id' => $branch->id,
                'entry_date' => $today,
                'journal_entry_id' => $journalEntry->id,
                'debit' => $debit,
                'credit' => '0.0000',
                'running_balance' => $debit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $service = $this->app->make(LedgerService::class);
        $result = $service->getAccountLedger('8400', $today, $today);

        $this->assertSame('0.3000', $result['total_debits']);
        $this->assertSame('0.0000', $result['total_credits']);
    }

    #[Test]
    public function journal_entry_threads_branch_id_into_ledger(): void
    {
        $branch = Branch::factory()->create(['code' => 'HQ', 'name' => 'Headquarters']);

        $asset = ChartOfAccount::updateOrCreate(
            ['account_code' => '8500'],
            ['account_type' => 'Asset', 'account_name' => 'Cash', 'is_active' => true]
        );
        $revenue = ChartOfAccount::updateOrCreate(
            ['account_code' => '8600'],
            ['account_type' => 'Revenue', 'account_name' => 'Revenue', 'is_active' => true]
        );

        FiscalYear::factory()->create([
            'year_code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService, new CacheInvalidationService);

        $entry = $service->createJournalEntry(
            [
                ['account_code' => '8500', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '8600', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Manual',
            null,
            'Branch-scoped entry',
            '2026-01-15',
            $user->id,
            $branch->id
        );

        $this->assertEquals($branch->id, $entry->branch_id);

        $ledgerRows = AccountLedger::where('journal_entry_id', $entry->id)->get();
        $this->assertCount(2, $ledgerRows);
        foreach ($ledgerRows as $row) {
            $this->assertEquals($branch->id, $row->branch_id);
        }

        // Activity via SQL aggregate must equal debits - credits.
        $activity = $service->getAccountActivity('8500', '2026-01-01', '2026-01-31');
        $this->assertSame('1000.0000', $activity);
    }

    #[Test]
    public function journal_reversal_preserves_branch_id(): void
    {
        // Regression: reverseJournalEntry() used to call createJournalEntry()
        // without the original entry's branch_id, so reversal ledger rows were
        // posted with NULL branch_id and branch-scoped reports never zeroed out.
        $branch = Branch::factory()->create(['code' => 'HQ', 'name' => 'Headquarters']);

        ChartOfAccount::updateOrCreate(
            ['account_code' => '8700'],
            ['account_type' => 'Asset', 'account_name' => 'Cash', 'is_active' => true]
        );
        ChartOfAccount::updateOrCreate(
            ['account_code' => '8800'],
            ['account_type' => 'Revenue', 'account_name' => 'Revenue', 'is_active' => true]
        );

        FiscalYear::factory()->create([
            'year_code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService, new CacheInvalidationService);

        $entry = $service->createJournalEntry(
            [
                ['account_code' => '8700', 'debit' => '500.00', 'credit' => '0'],
                ['account_code' => '8800', 'debit' => '0', 'credit' => '500.00'],
            ],
            'Manual',
            null,
            'Original entry',
            '2026-01-15',
            $user->id,
            $branch->id
        );

        $reversal = $service->reverseJournalEntry($entry, 'Test reversal', $user->id);

        $this->assertEquals($branch->id, $reversal->branch_id);
        $reversalLedger = AccountLedger::where('journal_entry_id', $reversal->id)->get();
        $this->assertCount(2, $reversalLedger);
        foreach ($reversalLedger as $row) {
            $this->assertEquals($branch->id, $row->branch_id);
        }
    }

    #[Test]
    public function export_csv_neutralizes_formula_injection(): void
    {
        $service = new ExportService;

        $path = $service->toCSV([
            ['name' => '=SUM(A1:A9)', 'qty' => '+1+2', 'note' => '@cmd', 'minus' => '-2+3', 'tab' => "\tcmd", 'safe' => 'plain'],
        ], 'formula-test-'.uniqid().'.csv');

        $content = file_get_contents($path);
        unlink($path);

        $this->assertStringContainsString("'=SUM(A1:A9)", $content);
        $this->assertStringContainsString("'+1+2", $content);
        $this->assertStringContainsString("'@cmd", $content);
        $this->assertStringContainsString("'-2+3", $content);
        $this->assertStringContainsString("'\tcmd", $content);
        $this->assertStringContainsString('plain', $content);
    }

    #[Test]
    public function stock_transfer_rejects_over_receipt(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $currency = Currency::factory()->create(['code' => 'USD']);

        $transfer = StockTransfer::factory()->create(['status' => StockTransferStatus::InTransit]);
        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'currency_code' => $currency->code,
            'quantity' => '100.00',
            'quantity_received' => '0.0000',
            'quantity_in_transit' => '100.0000',
        ]);

        $service = new StockTransferService(new MathService, new AuditService, new CurrencyPositionLockService(new MathService), new BranchPoolService(new AuditService, new MathService), new AccountingService(new MathService, new AuditService, new CacheInvalidationService), new AccountMappingService(new CacheInvalidationService, new AuditService), $admin);

        $this->expectException(TransactionValidationException::class);
        $this->expectExceptionMessage('exceeds the transferred quantity');

        $service->receiveItems($transfer, [
            ['id' => $item->id, 'quantity_received' => '150.00'],
        ]);
    }

    #[Test]
    public function stock_transfer_rejects_negative_receipt(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $currency = Currency::factory()->create(['code' => 'XJP']);

        $transfer = StockTransfer::factory()->create(['status' => StockTransferStatus::InTransit]);
        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'currency_code' => $currency->code,
            'quantity' => '100.00',
            'quantity_received' => '0.0000',
            'quantity_in_transit' => '100.0000',
        ]);

        $service = new StockTransferService(new MathService, new AuditService, new CurrencyPositionLockService(new MathService), new BranchPoolService(new AuditService, new MathService), new AccountingService(new MathService, new AuditService, new CacheInvalidationService), new AccountMappingService(new CacheInvalidationService, new AuditService), $admin);

        $this->expectException(TransactionValidationException::class);
        $this->expectExceptionMessage('cannot be negative');

        $service->receiveItems($transfer, [
            ['id' => $item->id, 'quantity_received' => '-5.00'],
        ]);
    }

    #[Test]
    public function stock_transfer_accepts_partial_receipt(): void
    {
        // The seal job is deferred to afterCommit; on the sync driver it
        // executes inline at commit() and throws when it meets an unsealed
        // gap (the plain log() entry written between sealed ones — sealed
        // later by the audit:seal-pending sweeper, retried by the job on
        // queued drivers). Faking it keeps this test focused on the
        // partial-receipt accounting, not seal ordering.
        Queue::fake([SealAuditHashJob::class]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $currency = Currency::factory()->create(['code' => 'EUR']);

        $transfer = StockTransfer::factory()->create(['status' => StockTransferStatus::InTransit]);
        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'currency_code' => $currency->code,
            'quantity' => '100.00',
            'quantity_received' => '0.0000',
            'quantity_in_transit' => '100.0000',
        ]);

        $service = new StockTransferService(new MathService, new AuditService, new CurrencyPositionLockService(new MathService), new BranchPoolService(new AuditService, new MathService), new AccountingService(new MathService, new AuditService, new CacheInvalidationService), new AccountMappingService(new CacheInvalidationService, new AuditService), $admin);

        $service->receiveItems($transfer, [
            ['id' => $item->id, 'quantity_received' => '60.00'],
        ]);

        $item->refresh();
        $this->assertSame('60.0000', $item->quantity_received);
        $this->assertSame('40.0000', $item->quantity_in_transit);
        $transfer->refresh();
        $this->assertEquals(StockTransferStatus::PartiallyReceived, $transfer->status);
    }

    #[Test]
    public function monitoring_engine_can_construct_every_registered_monitor(): void
    {
        // Regression: getMonitor() previously constructed every monitor with a
        // fixed (MathService, ComplianceService) argument list. Monitors with
        // 1-arg or 3-arg constructors threw ArgumentCountError/TypeError, so the
        // entire compliance sweep silently produced zero findings.
        $monitors = [
            VelocityMonitor::class,
            StructuringMonitor::class,
            SanctionsRescreeningMonitor::class,
            CustomerLocationAnomalyMonitor::class,
            CurrencyFlowMonitor::class,
            CounterfeitAlertMonitor::class,
        ];

        $engine = app(MonitoringEngine::class);

        foreach ($monitors as $monitorClass) {
            $monitor = $engine->getMonitor($monitorClass);
            $this->assertInstanceOf(BaseMonitor::class, $monitor);
        }
    }

    #[Test]
    public function location_anomaly_monitor_sums_amounts_with_bcmath(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'Singaporean',
            'is_active' => true,
        ]);

        $currencies = collect([
            Currency::factory()->create(['code' => 'XUS']),
            Currency::factory()->create(['code' => 'XEU']),
            Currency::factory()->create(['code' => 'XGB']),
        ]);

        // 3 currencies in 7 days at >= threshold triggers the anomaly.
        foreach ($currencies as $i => $currency) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'currency_code' => $currency->code,
                'amount_myr' => '50000.10',
                'quantity' => '10000.00',
                'created_at' => now()->subDays($i),
            ]);
        }

        $monitor = new CustomerLocationAnomalyMonitor(new ThresholdService, app(AlertTriageService::class));
        $findings = $monitor->run();

        $this->assertCount(1, $findings);
        $this->assertSame('150000.3000', $findings[0]['details']['total_amount_myr']);
    }
}
