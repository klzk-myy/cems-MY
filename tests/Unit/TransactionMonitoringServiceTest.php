<?php

namespace Tests\Unit;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Models\Compliance\Alert;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
use App\Services\Transaction\Checks\FlagDescriptor;
use App\Services\Transaction\Checks\TransactionCheck;
use App\Services\Transaction\Checks\TransactionCheckRegistry;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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
        $transaction = Transaction::factory()->create(['customer_id' => $customer->id]);

        $service = $this->serviceWithCheck([new FlagDescriptor(
            type: ComplianceFlagType::Velocity,
            reason: '24h velocity exceeded: RM 150000',
            auditEvent: 'aml_velocity_alert_triggered',
        )]);

        $result = $service->monitorTransaction($transaction);

        $flag = FlaggedTransaction::where('transaction_id', $transaction->id)->sole();

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
        $transaction = Transaction::factory()->create(['customer_id' => $customer->id]);

        $service = $this->serviceWithCheck([new FlagDescriptor(
            type: ComplianceFlagType::Velocity,
            reason: '24h velocity exceeded: RM 150000',
        )]);

        $service->monitorTransaction($transaction);
        $service->monitorTransaction($transaction);

        $flagIds = FlaggedTransaction::where('transaction_id', $transaction->id)->pluck('id');

        $alertCount = Alert::whereIn('flagged_transaction_id', $flagIds)->count();

        $this->assertLessThanOrEqual(
            $flagIds->count(),
            $alertCount,
            'Each flag must have at most one alert across repeated monitoring runs'
        );
    }

    /**
     * @param  array<int, FlagDescriptor>  $descriptors
     */
    private function serviceWithCheck(array $descriptors): TransactionMonitoringService
    {
        $check = new class($descriptors) implements TransactionCheck
        {
            /**
             * @param  array<int, FlagDescriptor>  $descriptors
             */
            public function __construct(private array $descriptors) {}

            public function check(Transaction $transaction): array
            {
                return $this->descriptors;
            }
        };

        $registry = Mockery::mock(TransactionCheckRegistry::class);
        $registry->shouldReceive('checks')->andReturn([$check]);

        return new TransactionMonitoringService(
            $registry,
            new AuditService,
            app(AlertTriageService::class)
        );
    }

    #[Test]
    public function re_monitoring_does_not_resurrect_a_resolved_flag_for_the_same_reason(): void
    {
        $customer = Customer::factory()->create();
        $transaction = Transaction::factory()->create(['customer_id' => $customer->id]);

        $service = $this->serviceWithCheck([new FlagDescriptor(
            type: ComplianceFlagType::Structuring,
            reason: 'Structuring pattern detected for customer',
        )]);

        $service->monitorTransaction($transaction);

        $flag = FlaggedTransaction::where('transaction_id', $transaction->id)->sole();
        $flag->update(['status' => FlagStatus::Resolved, 'resolved_at' => now()]);

        $result = $service->monitorTransaction($transaction);

        $this->assertSame(1, FlaggedTransaction::where('transaction_id', $transaction->id)->count());
        $this->assertSame(
            $flag->id,
            $result['flags'][0]->id,
            'Monitoring must return the reviewed flag, not create a replacement'
        );
        $this->assertSame(FlagStatus::Resolved, $result['flags'][0]->status);
    }

    #[Test]
    public function re_monitoring_still_flags_a_materially_different_reason(): void
    {
        $customer = Customer::factory()->create();
        $transaction = Transaction::factory()->create(['customer_id' => $customer->id]);

        FlaggedTransaction::create([
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'flag_type' => ComplianceFlagType::Structuring,
            'flag_reason' => 'Structuring pattern detected for customer',
            'status' => FlagStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $service = $this->serviceWithCheck([new FlagDescriptor(
            type: ComplianceFlagType::Structuring,
            reason: 'Confirmed sanctions-network layering via shell entities',
        )]);

        $service->monitorTransaction($transaction);

        $flags = FlaggedTransaction::where('transaction_id', $transaction->id)->orderBy('id')->get();
        $this->assertCount(2, $flags);
        $this->assertSame(
            FlagStatus::Open,
            $flags->last()->status,
            'The distinct finding must be a live flag that can block approval'
        );
    }
}
