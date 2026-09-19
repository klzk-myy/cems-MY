<?php

namespace Tests\Unit\Services\Risk;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Risk\AmountRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AmountRiskServiceTest extends TestCase
{
    use RefreshDatabase;

    private AmountRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AmountRiskService::class);
    }

    #[Test]
    public function calculate_score_returns_zero_for_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $this->assertSame(0, $this->service->calculateScore(collect(), $customer));
    }

    #[Test]
    public function exceeds_threshold_returns_true_when_amount_exceeds(): void
    {
        $this->assertTrue($this->service->exceedsThreshold('60000.00', '50000.00'));
    }

    #[Test]
    public function exceeds_threshold_returns_false_when_amount_is_below(): void
    {
        $this->assertFalse($this->service->exceedsThreshold('40000.00', '50000.00'));
    }

    #[Test]
    public function exceeds_threshold_returns_true_when_amount_equals_threshold(): void
    {
        $this->assertTrue($this->service->exceedsThreshold('50000.00', '50000.00'));
    }

    #[Test]
    public function get_max_amount_returns_zero_for_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $this->assertEquals('0', $this->service->getMaxAmount($customer->id));
    }

    #[Test]
    public function get_max_amount_returns_highest_completed_transaction(): void
    {
        $customer = Customer::factory()->create();

        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_myr' => '10000.00',
            'status' => TransactionStatus::Completed->value,
        ]);
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_myr' => '50000.00',
            'status' => TransactionStatus::Completed->value,
        ]);

        $this->assertEquals('50000', $this->service->getMaxAmount($customer->id));
    }
}
