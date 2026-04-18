<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\HistoricalRiskAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalRiskAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HistoricalRiskAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HistoricalRiskAnalysisService::class);
    }

    public function test_detects_velocity_risk(): void
    {
        $customer = Customer::factory()->create();

        // Create 3 recent transactions
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(2),
        ]);

        $result = $this->service->analyze($customer, '1000.00');

        $flags = $result->getFlags();
        $this->assertNotEmpty($flags);
        $this->assertEquals('velocity', $flags[0]['type']);
    }

    public function test_detects_structuring_risk(): void
    {
        $customer = Customer::factory()->create();

        // Create 2 transactions just below RM 3,000
        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'amount_local' => '2900.00',
            'created_at' => now()->subMinutes(30),
        ]);

        $result = $this->service->analyze($customer, '1000.00');

        $flags = $result->getFlags();
        $this->assertNotEmpty($flags);
        $this->assertEquals('structuring', $flags[0]['type']);
        $this->assertEquals('critical', $flags[0]['severity']);
    }

    public function test_detects_pattern_reversal(): void
    {
        $customer = Customer::factory()->create();

        // Create buys: some older, some newer than the sells
        // Older buys (before first sell)
        Transaction::factory()->count(4)->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy,
            'created_at' => now()->subDays(6),
        ]);

        // First sell (older)
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Sell,
            'created_at' => now()->subDays(5),
        ]);

        // More buys (newer than first sell but older than last sell)
        Transaction::factory()->count(4)->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy,
            'created_at' => now()->subDays(4),
        ]);

        // Final sell (most recent)
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Sell,
            'created_at' => now()->subHours(12),
        ]);

        $result = $this->service->analyze($customer, '1000.00');

        $flags = $result->getFlags();
        $patternFlags = array_filter($flags, fn ($f) => $f['type'] === 'pattern_reversal');
        $this->assertNotEmpty($patternFlags);
    }

    public function test_no_flags_for_new_customer(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->analyze($customer, '1000.00');

        $this->assertEmpty($result->getFlags());
        $this->assertFalse($result->hasCriticalFlags());
    }
}
