<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionServicePreValidationTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionService::class);
    }

    #[Test]
    public function sanctions_block_stops_validation(): void
    {
        $customer = Customer::factory()->create(['sanction_hit' => true]);

        $result = $this->service->preValidate($customer, '1000.00', 'USD');

        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->isHoldRequired());
    }

    #[Test]
    public function enhanced_cdd_requires_hold(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'sanction_hit' => false,
        ]);

        $result = $this->service->preValidate($customer, '60000.00', 'USD');

        $this->assertFalse($result->isBlocked());
        $this->assertTrue($result->isHoldRequired());
        $this->assertEquals(CddLevel::Enhanced, $result->getCDDLevel());
    }

    #[Test]
    public function standard_cdd_no_hold(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);

        // Amount >= 10000 MYR = Standard CDD per pd-00.md 14C.12.2
        $result = $this->service->preValidate($customer, '12000.00', 'USD');

        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->isHoldRequired());
        $this->assertEquals(CddLevel::Standard, $result->getCDDLevel());
    }

    #[Test]
    public function simplified_cdd_no_hold(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);

        $result = $this->service->preValidate($customer, '1000.00', 'USD');

        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->isHoldRequired());
        $this->assertEquals(CddLevel::Simplified, $result->getCDDLevel());
    }

    #[Test]
    public function returning_customer_has_risk_analysis(): void
    {
        $customer = Customer::factory()->create();

        // Create 3 recent transactions
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(2),
        ]);

        $result = $this->service->preValidate($customer, '1000.00', 'USD');

        $this->assertNotEmpty($result->getRiskFlags());
        $this->assertTrue(in_array('velocity', array_column($result->getRiskFlags(), 'type')));
    }
}
