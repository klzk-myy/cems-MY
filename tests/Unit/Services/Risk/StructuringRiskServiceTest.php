<?php

namespace Tests\Unit\Services\Risk;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Risk\StructuringRiskService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StructuringRiskServiceTest extends TestCase
{
    use RefreshDatabase;

    private StructuringRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StructuringRiskService(
            new MathService,
            app(ThresholdService::class)
        );
    }

    #[Test]
    public function calculate_score_returns_zero_when_no_transactions_exist(): void
    {
        $customer = Customer::factory()->create();

        $this->assertSame(0, $this->service->calculateScore($customer->id, 24));
    }

    #[Test]
    public function calculate_score_returns_zero_when_transactions_are_above_sub_threshold(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold + '1000'),
            'created_at' => now()->subHours(3),
        ]);

        $this->assertSame(0, $this->service->calculateScore($customer->id, 24));
    }

    #[Test]
    public function calculate_score_ignores_cancelled_transactions(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Cancelled->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subHours(3),
        ]);

        $this->assertSame(0, $this->service->calculateScore($customer->id, 24));
    }

    #[Test]
    public function calculate_score_adds_25_when_three_or_more_sub_threshold_transactions_in_one_hour(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subMinutes(30),
        ]);

        $this->assertSame(25, $this->service->calculateScore($customer->id, 24));
    }

    #[Test]
    public function calculate_score_adds_10_when_exactly_two_sub_threshold_transactions_in_one_hour(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subMinutes(30),
        ]);

        $this->assertSame(10, $this->service->calculateScore($customer->id, 24));
    }

    #[Test]
    public function calculate_score_caps_at_30(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        // Two hourly groups of 3+ transactions each would total 50 (25+25), which must cap at 30.
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subHours(3),
        ]);

        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subHours(1),
        ]);

        $this->assertSame(30, $this->service->calculateScore($customer->id, 24));
    }

    #[Test]
    public function calculate_score_uses_configured_window_hours(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subHours(5),
        ]);

        // 24h window includes the transactions
        $this->assertSame(25, $this->service->calculateScore($customer->id, 24));

        // 2h window excludes them
        $this->assertSame(0, $this->service->calculateScore($customer->id, 2));
    }

    #[Test]
    public function check_threshold_returns_triggered_true_when_count_meets_threshold(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subMinutes(30),
        ]);

        $result = $this->service->checkThreshold($customer->id, 24, 3);

        $this->assertTrue($result['triggered']);
        $this->assertSame(3, $result['count']);
        $this->assertSame(3, $result['threshold']);
    }

    #[Test]
    public function check_threshold_returns_triggered_false_when_count_below_threshold(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subMinutes(30),
        ]);

        $result = $this->service->checkThreshold($customer->id, 24, 3);

        $this->assertFalse($result['triggered']);
        $this->assertSame(2, $result['count']);
    }

    #[Test]
    public function is_structuring_returns_true_when_three_transactions_under_threshold_in_one_hour(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subMinutes(30),
        ]);

        $this->assertTrue($this->service->isStructuring($customer->id));
    }

    #[Test]
    public function is_structuring_returns_false_when_two_transactions_under_threshold_in_one_hour(): void
    {
        $customer = Customer::factory()->create();
        $subThreshold = app(ThresholdService::class)->getStructuringSubThreshold();

        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Completed->value,
            'amount_myr' => (float) ((string) $subThreshold - '100'),
            'created_at' => now()->subMinutes(30),
        ]);

        $this->assertFalse($this->service->isStructuring($customer->id));
    }
}
