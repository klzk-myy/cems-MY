<?php

namespace Tests\Feature\Reporting;

use App\Enums\JournalEntryStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::makeDirectory('reports');
    }

    public function test_msb2_command_writes_csv_for_the_given_date(): void
    {
        $date = '2026-03-10';

        $code = Artisan::call('report:msb2', ['--date' => $date]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('MSB(2) report generated', Artisan::output());

        $this->assertFileExists(storage_path("app/reports/MSB2_{$date}.csv"));
    }

    /**
     * createReportRecord() writes reports_generated.generated_by from
     * cems.system_user_id — point it at a real user or the FK fails.
     */
    protected function seedSystemUser(): void
    {
        config(['cems.system_user_id' => User::factory()->create()->id]);
    }

    public function test_lmca_command_writes_csv_for_the_given_month(): void
    {
        config(['cems.license_number' => 'TEST-LICENSE-001']);
        $this->seedSystemUser();

        $code = Artisan::call('report:lmca', ['--month' => '2026-02']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Form LMCA generated', Artisan::output());

        $this->assertFileExists(storage_path('app/reports/LMCA_2026-02.csv'));
    }

    public function test_qlvr_command_writes_csv_for_the_given_quarter(): void
    {
        config(['thresholds.cdd.large_transaction' => '10000']);
        $this->seedSystemUser();

        $code = Artisan::call('report:qlvr', ['--quarter' => '2026-Q1']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Quarterly LVR generated', Artisan::output());

        $this->assertFileExists(storage_path('app/reports/QLVR_2026-Q1.csv'));
    }

    public function test_position_limit_command_writes_csv(): void
    {
        $this->seedSystemUser();

        $code = Artisan::call('report:position-limit');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Position Limit Report generated', Artisan::output());
    }

    public function test_trial_balance_command_writes_csv_and_records_run(): void
    {
        $this->seedSystemUser();

        $entry = JournalEntry::factory()->create([
            'status' => JournalEntryStatus::Posted,
            'entry_date' => '2026-02-10',
        ]);
        JournalLine::factory()->for($entry)->create(['debit' => '100.0000', 'credit' => '0']);
        JournalLine::factory()->for($entry)->create(['debit' => '0', 'credit' => '100.0000']);

        $code = Artisan::call('report:trial-balance', ['--date' => '2026-02-15']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Trial Balance generated', Artisan::output());
        $this->assertDatabaseHas('reports_generated', [
            'report_type' => 'trial_balance',
        ]);
    }

    public function test_eod_command_writes_json_report(): void
    {
        $date = '2026-03-10';
        $this->createCompletedTransaction($date);

        $code = Artisan::call('report:eod', ['--date' => $date, '--format' => 'json']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('EOD reconciliation report generated', Artisan::output());

        $this->assertFileExists(storage_path("app/reports/EOD-Reconciliation-{$date}.json"));
    }

    protected function createCompletedTransaction(string $date): Transaction
    {
        $transaction = Transaction::factory()
            ->for(Branch::factory()->create())
            ->for(Customer::factory()->create())
            ->create([
                'type' => TransactionType::Buy->value,
                'status' => TransactionStatus::Completed->value,
                'currency_code' => 'USD',
                'quantity' => '100.0000',
                'rate' => '4.0000',
                'amount_myr' => '400.0000',
            ]);

        $transaction->created_at = Carbon::parse($date)->startOfDay()->addHours(10);
        $transaction->saveQuietly();

        return $transaction;
    }
}
