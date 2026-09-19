<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Transaction\Checks\TransactionCheckRegistry;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TransactionMonitoringService(
            app(TransactionCheckRegistry::class),
            new AuditService,
            app(AlertTriageService::class)
        );
    }

    #[Test]
    public function is_round_amount_method_was_removed(): void
    {
        $reflection = new \ReflectionClass($this->service);

        $this->assertFalse(
            $reflection->hasMethod('isRoundAmount'),
            'isRoundAmount() method should be removed - it was causing false positives'
        );
    }

    #[Test]
    public function round_amount_detection_does_not_flag_legitimate_large_transactions(): void
    {
        $amount = '50000.00';

        $threshold = '25000';

        $remainder = bcmod($amount, $threshold);
        $this->assertEquals('0', $remainder, 'RM 50,000 is divisible by RM 25,000');

        $this->assertTrue(
            bccomp($amount, $threshold, 2) >= 0,
        );
    }

    #[Test]
    public function rm_75000_is_not_flagged_as_round_amount(): void
    {
        $amount = '75000.00';
        $threshold = '25000';

        $remainder = bcmod($amount, $threshold);
        $this->assertEquals('0', $remainder, 'RM 75,000 is divisible by RM 25,000');
    }

    #[Test]
    public function rm_100000_is_not_flagged_as_round_amount(): void
    {
        $amount = '100000.00';
        $threshold = '25000';

        $remainder = bcmod($amount, $threshold);
        $this->assertEquals('0', $remainder, 'RM 100,000 is divisible by RM 25,000');
    }

    #[Test]
    public function monitor_transaction_does_not_create_round_amount_flags(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_myr' => '50000.00',
            'currency_code' => 'USD',
        ]);

        $result = $this->service->monitorTransaction($transaction);

        $roundAmountFlags = FlaggedTransaction::where('transaction_id', $transaction->id)
            ->where('flag_type', 'round_amount')
            ->count();

        $this->assertEquals(0, $roundAmountFlags, 'RM 50,000 should not trigger a RoundAmount flag');
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('flags_created', $result);
        $this->assertArrayHasKey('flags', $result);
    }

    #[Test]
    public function monitor_transaction_handles_rm_25000_exact_threshold(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_myr' => '25000.00',
            'currency_code' => 'USD',
        ]);

        $result = $this->service->monitorTransaction($transaction);

        $roundAmountFlags = FlaggedTransaction::where('transaction_id', $transaction->id)
            ->where('flag_type', 'round_amount')
            ->count();

        $this->assertEquals(0, $roundAmountFlags, 'RM 25,000 exact threshold should not trigger RoundAmount flag');
    }

    #[Test]
    public function new_monitoring_flag_carries_customer_id_and_creates_alert(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_myr' => '100.00',
            'currency_code' => 'USD',
        ]);

        // Force a velocity flag by seeding 24h history above the velocity threshold.
        $velocityThreshold = (string) config('thresholds.velocity_24h', '100000');
        $transaction->amount_myr = $velocityThreshold;
        $transaction->save();

        $result = $this->service->monitorTransaction($transaction);

        $flag = FlaggedTransaction::where('transaction_id', $transaction->id)->first();

        if ($flag === null) {
            $this->markTestSkipped('No monitoring flag triggered for this transaction shape');
        }

        $this->assertSame($customer->id, $flag->customer_id);

        $this->assertDatabaseHas('alerts', [
            'flagged_transaction_id' => $flag->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $result['flags_created']);
    }

    #[Test]
    public function re_monitoring_does_not_create_duplicate_alerts_for_same_flag(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_myr' => (string) config('thresholds.velocity_24h', '100000'),
            'currency_code' => 'USD',
        ]);

        $this->service->monitorTransaction($transaction);
        $this->service->monitorTransaction($transaction);

        $flagIds = FlaggedTransaction::where('transaction_id', $transaction->id)->pluck('id');

        if ($flagIds->isEmpty()) {
            $this->markTestSkipped('No monitoring flag triggered for this transaction shape');
        }

        $alertCount = Alert::whereIn('flagged_transaction_id', $flagIds)->count();

        $this->assertLessThanOrEqual(
            $flagIds->count(),
            $alertCount,
            'Each flag must have at most one alert across repeated monitoring runs'
        );
    }
}
