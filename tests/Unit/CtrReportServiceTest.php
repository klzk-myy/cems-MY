<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\CtrReportService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CtrReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CtrReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $mockThreshold = Mockery::mock(ThresholdService::class);
        $mockThreshold->shouldReceive('getCtrThreshold')->andReturn('25000');
        $this->service = new CtrReportService($mockThreshold);
    }

    public function test_check_threshold_returns_not_exceeded_for_small_amount(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 5000,
        ]);

        $result = $this->service->checkThreshold($transaction);

        $this->assertEquals('not_exceeded', $result['status']);
        $this->assertEquals(25000, $result['threshold']);
        $this->assertNull($result['message']);
    }

    public function test_check_threshold_returns_approaching_at_20000(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 20000,
        ]);

        $result = $this->service->checkThreshold($transaction);

        $this->assertEquals('approaching', $result['status']);
        $this->assertEquals(25000, $result['threshold']);
        $this->assertNotNull($result['message']);
    }

    public function test_check_threshold_returns_exceeded_at_25000(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 25000,
        ]);

        $result = $this->service->checkThreshold($transaction);

        $this->assertEquals('exceeded', $result['status']);
        $this->assertEquals(25000, $result['threshold']);
        $this->assertStringContainsString('exceeded', $result['message']);
    }

    public function test_check_threshold_returns_exceeded_above_25000(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 50000,
        ]);

        $result = $this->service->checkThreshold($transaction);

        $this->assertEquals('exceeded', $result['status']);
    }

    public function test_get_daily_total_returns_zero_for_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $total = $this->service->getDailyTotal($customer, now()->toDateString());

        $this->assertEquals('0.00', $total);
    }

    public function test_get_daily_total_sums_buy_transactions(): void
    {
        $customer = Customer::factory()->create();

        Transaction::factory()->buy()->completed()->create([
            'customer_id' => $customer->id,
            'amount_local' => 10000,
            'created_at' => now(),
        ]);

        Transaction::factory()->buy()->completed()->create([
            'customer_id' => $customer->id,
            'amount_local' => 15000,
            'created_at' => now(),
        ]);

        Transaction::factory()->sell()->completed()->create([
            'customer_id' => $customer->id,
            'amount_local' => 5000,
            'created_at' => now(),
        ]);

        $total = $this->service->getDailyTotal($customer, now()->toDateString());

        $this->assertEquals('25000.00', $total);
    }

    public function test_get_daily_aggregates_returns_customers_over_threshold(): void
    {
        $customer1 = Customer::factory()->create(['full_name' => 'Customer One']);
        $customer2 = Customer::factory()->create(['full_name' => 'Customer Two']);

        Transaction::factory()->buy()->completed()->create([
            'customer_id' => $customer1->id,
            'amount_local' => 30000,
        ]);

        Transaction::factory()->buy()->completed()->create([
            'customer_id' => $customer2->id,
            'amount_local' => 5000,
        ]);

        $grouped = Transaction::whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['Completed', 'approved'])
            ->where('type', 'Buy')
            ->selectRaw('customer_id, SUM(amount_local) as total_amount, COUNT(id) as transaction_count')
            ->groupBy('customer_id')
            ->get();

        $this->assertEquals(2, $grouped->count(), 'Grouped count: '.$grouped->count());

        $aggregates = $this->service->getDailyCtrAggregates(now()->toDateString());

        $this->assertGreaterThanOrEqual(1, $aggregates->count());
    }

    public function test_generate_ctr_report_returns_correct_structure(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Test Customer']);

        Transaction::factory()->buy()->completed()->create([
            'customer_id' => $customer->id,
            'amount_local' => 30000,
            'created_at' => now(),
        ]);

        $report = $this->service->generateCtrReport(now()->toDateString());

        $this->assertArrayHasKey('report_date', $report);
        $this->assertArrayHasKey('threshold', $report);
        $this->assertArrayHasKey('total_customers_above_threshold', $report);
        $this->assertArrayHasKey('customers', $report);
        $this->assertEquals(now()->toDateString(), $report['report_date']);
    }

    public function test_is_transaction_above_threshold(): void
    {
        $transaction1 = Transaction::factory()->create(['amount_local' => 30000]);
        $transaction2 = Transaction::factory()->create(['amount_local' => 20000]);

        $this->assertTrue($this->service->isTransactionAboveThreshold($transaction1));
        $this->assertFalse($this->service->isTransactionAboveThreshold($transaction2));
    }
}
