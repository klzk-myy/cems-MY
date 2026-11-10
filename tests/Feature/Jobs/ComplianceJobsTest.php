<?php

namespace Tests\Feature\Jobs;

use App\Enums\SystemAlertLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\Compliance\ComputeBehavioralBaselineJob;
use App\Jobs\Compliance\LowStockAlertJob;
use App\Jobs\Compliance\RunComplianceMonitorJob;
use App\Models\Branch;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\SystemAlert;
use App\Models\Transaction;
use App\Services\Compliance\MonitoringEngine;
use App\Services\System\SystemAlertService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceJobsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function low_stock_alert_job_creates_warning_alert_for_currency_below_threshold(): void
    {
        config(['thresholds.low_stock.threshold' => '10000']);

        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => null,
            'quantity' => '5000.0000',
        ]);

        (new LowStockAlertJob)->handle(app(SystemAlertService::class), app(ThresholdService::class));

        $this->assertDatabaseHas('system_alerts', [
            'source' => 'low_stock',
            'level' => SystemAlertLevel::Warning->value,
        ]);
    }

    #[Test]
    public function low_stock_alert_job_stays_quiet_when_all_positions_meet_threshold(): void
    {
        config(['thresholds.low_stock.threshold' => '10000']);

        $branch = Branch::factory()->create();
        foreach (Currency::where('is_active', true)->pluck('code') as $code) {
            CurrencyPosition::factory()->for($branch)->create([
                'currency_code' => $code,
                'quantity' => '50000.0000',
            ]);
        }

        (new LowStockAlertJob)->handle(app(SystemAlertService::class), app(ThresholdService::class));

        $this->assertSame(0, SystemAlert::where('source', 'low_stock')->count());
    }

    #[Test]
    public function baseline_job_computes_avg_size_frequency_and_currencies(): void
    {
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        foreach ([['400.00', 'USD'], ['600.00', 'EUR']] as [$amount, $code]) {
            Transaction::factory()->for($branch)->for($customer)->create([
                'type' => TransactionType::Buy->value,
                'status' => TransactionStatus::Completed->value,
                'currency_code' => $code,
                'amount_myr' => $amount,
            ]);
        }

        // Outside the 90-day window — must not influence the baseline.
        $old = Transaction::factory()->for($branch)->for($customer)->create([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'GBP',
            'amount_myr' => '99999.00',
        ]);
        $old->created_at = now()->subDays(100);
        $old->saveQuietly();

        (new ComputeBehavioralBaselineJob($customer->id))->handle();

        $baseline = CustomerBehavioralBaseline::where('customer_id', $customer->id)->sole();

        $this->assertEquals(500.0, (float) $baseline->avg_transaction_size_myr);
        $this->assertEquals('0.15', $baseline->avg_transaction_frequency);
        $this->assertEqualsCanonicalizing(['USD', 'EUR'], $baseline->currency_codes);
        $this->assertSame(1, $baseline->baseline_version);
    }

    #[Test]
    public function baseline_job_reruns_bump_version_without_duplicating_rows(): void
    {
        $customer = Customer::factory()->create();

        (new ComputeBehavioralBaselineJob($customer->id))->handle();
        (new ComputeBehavioralBaselineJob($customer->id))->handle();

        $this->assertSame(1, CustomerBehavioralBaseline::where('customer_id', $customer->id)->count());
        $this->assertSame(2, CustomerBehavioralBaseline::where('customer_id', $customer->id)->sole()->baseline_version);
    }

    #[Test]
    public function monitor_job_delegates_to_monitoring_engine(): void
    {
        $engine = $this->mock(MonitoringEngine::class);
        $engine->shouldReceive('runMonitor')->once()->with('App\\Monitors\\VelocityMonitor');

        (new RunComplianceMonitorJob('App\\Monitors\\VelocityMonitor'))->handle($engine);
    }
}
