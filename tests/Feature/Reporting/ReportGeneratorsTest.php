<?php

namespace Tests\Feature\Reporting;

use App\Enums\JournalEntryStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\ReportValidationException;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Services\Reporting\Generators\LedgerBackedReportGenerator;
use App\Services\Reporting\Generators\LmcaReportGenerator;
use App\Services\Reporting\Generators\Msb2ReportGenerator;
use App\Services\Reporting\Generators\PositionReportGenerator;
use App\Services\Reporting\Generators\QlvrReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportGeneratorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::makeDirectory('reports');
    }

    public function test_msb2_aggregates_daily_buy_sell_volumes_per_currency(): void
    {
        $date = '2026-03-10';
        $this->createCompletedTransaction('USD', '100.00', '4.00', TransactionType::Buy, $date);
        $this->createCompletedTransaction('USD', '100.00', '4.00', TransactionType::Buy, $date);
        $this->createCompletedTransaction('USD', '50.00', '4.00', TransactionType::Sell, $date);
        // Out-of-window noise must not leak into the report.
        $this->createCompletedTransaction('USD', '999.00', '4.00', TransactionType::Buy, '2026-03-11');
        $this->createCompletedTransaction('USD', '999.00', '4.00', TransactionType::Buy, $date, TransactionStatus::Cancelled);

        $data = app(Msb2ReportGenerator::class)->generateData($date);

        /** @var list<array<string, mixed>> $rows */
        $rows = $data['data'];
        $usd = collect($rows)->firstWhere('Currency', 'USD');

        $this->assertNotNull($usd);
        // Volumes are MYR: 2 × (100 @ 4.00) bought, 1 × (50 @ 4.00) sold.
        // Float-compared: SUM(DECIMAL) string precision differs sqlite/MySQL.
        $this->assertEquals(800.0, (float) $usd['Buy_Volume_MYR']);
        $this->assertSame(2, $usd['Buy_Count']);
        $this->assertEquals(200.0, (float) $usd['Sell_Volume_MYR']);
        $this->assertSame(1, $usd['Sell_Count']);
    }

    public function test_msb2_csv_writes_report_to_reports_disk(): void
    {
        $date = '2026-03-10';
        $this->createCompletedTransaction('USD', '100.00', '4.00', TransactionType::Buy, $date);

        $path = app(Msb2ReportGenerator::class)->generate($date);

        $this->assertSame("reports/MSB2_{$date}.csv", $path);
        $this->assertTrue(Storage::exists($path));

        $lines = file(Storage::path($path));
        if ($lines === false) {
            $this->fail('CSV file unreadable');
        }
        $rows = array_map('str_getcsv', $lines);

        $this->assertSame(['Date', 'Currency', 'Buy_Volume', 'Buy_Count', 'Sell_Volume', 'Sell_Count'], $rows[0]);
        $this->assertSame($date, $rows[1][0]);
        $this->assertSame('USD', $rows[1][1]);
        // SUM of a DECIMAL column returns '100' on sqlite, '100.0000' on MySQL.
        $this->assertEquals(100.0, (float) $rows[1][2]);
    }

    public function test_lmca_monthly_report_counts_and_positions(): void
    {
        config(['cems.license_number' => 'TEST-LICENSE-001']);

        $customer = Customer::factory()->create();
        $this->createCompletedTransaction('USD', '200.00', '4.00', TransactionType::Buy, '2026-02-05', TransactionStatus::Completed, null, $customer);
        $this->createCompletedTransaction('USD', '100.00', '4.00', TransactionType::Sell, '2026-02-20', TransactionStatus::Completed, null, $customer);

        $data = app(LmcaReportGenerator::class)->generate('2026-02');

        $this->assertSame('TEST-LICENSE-001', $data['license_number']);
        $this->assertSame('2026-02', $data['reporting_period']);
        $this->assertSame(1, $data['customer_count']);

        /** @var list<array<string, mixed>> $currencies */
        $currencies = $data['currencies'];
        $usd = collect($currencies)->firstWhere('currency_code', 'USD');
        $this->assertNotNull($usd);
        $this->assertSame(1, $usd['buy_count']);
        $this->assertSame(1, $usd['sell_count']);
        $this->assertSame('200.0000', $usd['buy_volume']);
        $this->assertSame('100.0000', $usd['sell_volume']);
        $this->assertSame('800.0000', $usd['buy_value_myr']);
        $this->assertSame('400.0000', $usd['sell_value_myr']);
    }

    public function test_lmca_throws_without_configured_license_number(): void
    {
        config(['cems.license_number' => null]);

        $this->expectException(ReportValidationException::class);

        app(LmcaReportGenerator::class)->generate('2026-02');
    }

    public function test_qlvr_only_includes_transactions_at_or_above_threshold(): void
    {
        config(['thresholds.cdd.large_transaction' => '10000']);

        // Quarter 2026-Q1: Jan 1 - Mar 31.
        $this->createCompletedTransaction('USD', '3000.00', '4.00', TransactionType::Buy, '2026-01-15', TransactionStatus::Completed, '12000.00');
        $this->createCompletedTransaction('USD', '2500.00', '4.00', TransactionType::Sell, '2026-02-10', TransactionStatus::Completed, '10000.00');
        // Below the threshold — must be excluded.
        $this->createCompletedTransaction('USD', '100.00', '4.00', TransactionType::Buy, '2026-01-15', TransactionStatus::Completed, '400.00');

        $data = app(QlvrReportGenerator::class)->generate('2026-Q1');

        $this->assertSame(2, $data['total_transactions']);
        $this->assertSame('22000.0000', $data['total_amount_myr']);
        $this->assertCount(3, $data['monthly_breakdown']);
        $this->assertSame(1, $data['monthly_breakdown'][0]['count']); // Jan
        $this->assertSame(1, $data['monthly_breakdown'][1]['count']); // Feb
        $this->assertSame(0, $data['monthly_breakdown'][2]['count']); // Mar
    }

    public function test_position_limit_report_computes_utilization_and_exposure(): void
    {
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => null,
            'quantity' => '500000.0000',
            'average_cost' => '4.7000',
            'current_rate' => '4.8000',
        ]);

        $data = app(PositionReportGenerator::class)->generatePositionLimitReport();

        /** @var list<array<string, mixed>> $positions */
        $positions = $data['positions'];
        $usd = collect($positions)->firstWhere('currency_code', 'USD');

        $this->assertNotNull($usd);
        // 500k of a 1M configured limit → 50% utilization.
        $this->assertSame('50.0000', $usd['utilization_percent']);
        // exposure = quantity × current_rate.
        $this->assertSame('2400000.0000', $usd['exposure_myr']);
        $this->assertSame(1, $data['summary']['total_currencies']);
    }

    public function test_trial_balance_csv_is_balanced_and_contains_account_rows(): void
    {
        $entry = JournalEntry::factory()->create([
            'status' => JournalEntryStatus::Posted,
            'entry_date' => '2026-02-10',
        ]);
        JournalLine::factory()->for($entry)->create(['debit' => '100.0000', 'credit' => '0']);
        JournalLine::factory()->for($entry)->create(['debit' => '0', 'credit' => '100.0000']);

        $path = app(LedgerBackedReportGenerator::class)->generateTrialBalance('2026-02');

        $this->assertTrue(Storage::exists($path));

        $csv = file_get_contents(Storage::path($path));
        if ($csv === false) {
            $this->fail('CSV file unreadable');
        }

        $this->assertStringContainsString('Trial Balance Report', $csv);
        $this->assertStringContainsString('Balanced,Yes', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
    }

    /**
     * Completed transaction on an exact business date; amount_myr defaults to
     * quantity × rate for the report aggregates that read it directly.
     */
    protected function createCompletedTransaction(
        string $currency,
        string $quantity,
        string $rate,
        TransactionType $type,
        string $date,
        TransactionStatus $status = TransactionStatus::Completed,
        ?string $amountMyr = null,
        ?Customer $customer = null,
    ): Transaction {
        $branch = Branch::factory()->create();
        $customer ??= Customer::factory()->create();

        if (Currency::where('code', $currency)->doesntExist()) {
            Currency::factory()->create(['code' => $currency, 'is_active' => true]);
        }

        $transaction = Transaction::factory()
            ->for($branch)
            ->for($customer)
            ->create([
                'type' => $type->value,
                'status' => $status->value,
                'currency_code' => $currency,
                'quantity' => $quantity,
                'rate' => $rate,
                'amount_myr' => $amountMyr ?? (string) ((float) $quantity * (float) $rate),
            ]);

        $transaction->created_at = Carbon::parse($date)->startOfDay()->addHours(10);
        $transaction->updated_at = $transaction->created_at;
        $transaction->saveQuietly();

        return $transaction;
    }
}
