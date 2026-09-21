<?php

namespace Tests\Feature\Compliance;

use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\SystemAlert;
use App\Models\Transaction;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\MonitoringEngine;
use App\Services\Compliance\Monitors\StructuringMonitor;
use App\Services\Risk\StructuringRiskService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 8 regressions (K1/K2): structuring findings require both the count
 * AND the aggregate amount; non-booked statuses don't count; monitor-failure
 * alert dedup survives across scheduled runs (separate engine instances).
 */
class StructuringMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function monitor(): StructuringMonitor
    {
        return new StructuringMonitor(
            new MathService,
            app(StructuringRiskService::class),
            new ThresholdService,
            app(AlertTriageService::class)
        );
    }

    #[Test]
    public function test_structuring_requires_aggregate_over_threshold(): void
    {
        $customer = Customer::factory()->create();

        // 4 sub-threshold bookings (min count met) but only 6,000 MYR total —
        // below the 8,000 aggregate trigger. No finding.
        Transaction::factory()->count(4)->create([
            'customer_id' => $customer->id,
            'amount_myr' => '1500.00',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now(),
        ]);

        $this->assertCount(0, $this->monitor()->run());

        // Same count, but the aggregate crosses the trigger.
        $structuring = Customer::factory()->create();
        Transaction::factory()->count(4)->create([
            'customer_id' => $structuring->id,
            'amount_myr' => '2900.00',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now(),
        ]);

        $findings = $this->monitor()->run();

        $this->assertCount(1, $findings);
        $this->assertSame($structuring->id, $findings[0]['subject_id']);
    }

    #[Test]
    public function test_failed_transactions_do_not_count_toward_structuring(): void
    {
        $customer = Customer::factory()->create();

        // 2 booked sub-threshold transactions — under min count and
        // aggregate. The 3 failed bookings must not be counted.
        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'amount_myr' => '2900.00',
            'status' => TransactionStatus::Completed->value,
            'created_at' => now(),
        ]);
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'amount_myr' => '2900.00',
            'status' => TransactionStatus::Failed->value,
            'created_at' => now(),
        ]);

        $this->assertCount(0, $this->monitor()->run());
    }

    #[Test]
    public function test_monitor_alert_dedupes_across_runs(): void
    {
        $failure = [
            'monitor' => 'App\Monitors\BrokenMonitor',
            'exception' => \RuntimeException::class,
            'message' => 'boom',
            'timestamp' => now()->toDateTimeString(),
        ];

        // Two distinct engine instances = two scheduled runs. The dedup must
        // come from shared (cache) state, not instance memory.
        $engineA = app(MonitoringEngine::class);
        $engineB = app(MonitoringEngine::class);

        $invoke = new \ReflectionMethod(MonitoringEngine::class, 'sendFailureNotification');
        $setLog = function (MonitoringEngine $engine, array $log): void {
            $ref = new \ReflectionProperty($engine, 'failureLog');
            $ref->setValue($engine, $log);
        };

        $setLog($engineA, [$failure]);
        $invoke->invoke($engineA, 1, ['App\Monitors\BrokenMonitor']);
        $this->assertDatabaseCount('system_alerts', 1);

        // Second run (fresh instance): same monitor fails again — no
        // duplicate alert.
        $setLog($engineB, [$failure]);
        $invoke->invoke($engineB, 1, ['App\Monitors\BrokenMonitor']);
        $this->assertDatabaseCount('system_alerts', 1);

        $this->assertStringContainsString('BrokenMonitor', SystemAlert::sole()->message);
    }

    #[Test]
    public function test_distinct_flag_reason_appends_instead_of_rewriting(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
        ]);

        $service = app(TransactionMonitoringService::class);
        $createFlag = new \ReflectionMethod($service, 'createFlag');
        $createFlag->setAccessible(true);

        $type = ComplianceFlagType::cases()[0];

        $first = $createFlag->invoke(
            $service, $transaction, $type,
            'Velocity pattern: many small cash exchanges in a short window'
        );
        $firstReason = $first->flag_reason;

        // A materially different suspicion on the same type must append a
        // second flag — the original reason is evidence and must not be
        // overwritten.
        $createFlag->invoke(
            $service, $transaction, $type,
            'Geographic anomaly inconsistent with customer profile'
        );

        $flags = FlaggedTransaction::where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $flags);
        $this->assertSame($firstReason, $flags[0]->flag_reason);
        $this->assertStringContainsString('Geographic anomaly', $flags[1]->flag_reason);

        // And a near-identical reason still dedupes to the first flag.
        $createFlag->invoke(
            $service, $transaction, $type,
            'Velocity pattern: many small cash exchanges in a short window!'
        );
        $this->assertSame(2, FlaggedTransaction::where('transaction_id', $transaction->id)->count());
    }
}
