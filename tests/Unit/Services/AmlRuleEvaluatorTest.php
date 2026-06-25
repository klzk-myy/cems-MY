<?php

namespace Tests\Unit\Services;

use App\Enums\AmlRuleType;
use App\Models\AmlRule;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Compliance\AmlRuleEvaluator;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AmlRuleEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private AmlRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new AmlRuleEvaluator(new MathService);
    }

    #[Test]
    public function test_velocity_rule_triggers_on_high_frequency(): void
    {
        $customer = Customer::factory()->create();
        $now = now();

        // Create 11 transactions in the last 24 hours (default threshold is 10)
        for ($i = 0; $i < 11; $i++) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'created_at' => $now->copy()->subHours(rand(0, 23)),
            ]);
        }

        // The 12th transaction to test
        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => $now,
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Velocity,
            'is_active' => true,
            'conditions' => [
                'window_hours' => 24,
                'max_transactions' => 10,
            ],
            'risk_score' => 50,
            'action' => 'flag',
            'rule_code' => 'VEL-001',
            'rule_name' => 'High Velocity',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(50, $result['risk_score']);
        $this->assertEquals('flag', $result['action']);
    }

    #[Test]
    public function test_velocity_rule_with_cumulative_threshold(): void
    {
        $customer = Customer::factory()->create();
        $now = now();

        // Create 5 transactions with cumulative amount of 100000 (threshold: 50000)
        $amounts = [15000, 15000, 20000, 20000, 30000];
        foreach ($amounts as $amount) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'amount_local' => $amount,
                'created_at' => $now->copy()->subHours(rand(0, 23)),
            ]);
        }

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => 5000,
            'created_at' => $now,
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Velocity,
            'is_active' => true,
            'conditions' => [
                'window_hours' => 24,
                'max_transactions' => 100, // High threshold to not trigger by count
                'cumulative_threshold' => '100000',
            ],
            'risk_score' => 60,
            'action' => 'hold',
            'rule_code' => 'VEL-002',
            'rule_name' => 'Cumulative Velocity',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(60, $result['risk_score']);
    }

    #[Test]
    public function test_structuring_rule_detects_split_transactions(): void
    {
        $customer = Customer::factory()->create();
        $now = now();

        // Create 3 transactions of 20000 each (total 60000, threshold 50000)
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'amount_local' => '20000',
                'created_at' => $now->copy()->subDay(),
                'status' => 'Completed',
            ]);
        }

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '20000',
            'created_at' => $now,
            'status' => 'Completed',
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Structuring,
            'is_active' => true,
            'conditions' => [
                'window_days' => 1,
                'min_transaction_count' => 3,
                'aggregate_threshold' => '50000',
            ],
            'risk_score' => 70,
            'action' => 'block',
            'rule_code' => 'STR-001',
            'rule_name' => 'Structuring Detection',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(70, $result['risk_score']);
    }

    #[Test]
    public function test_amount_threshold_triggers_on_large_transaction(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '100000',
            'currency_code' => 'MYR',
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::AmountThreshold,
            'is_active' => true,
            'conditions' => [
                'min_amount' => '50000',
                'currency' => 'MYR',
            ],
            'risk_score' => 40,
            'action' => 'flag',
            'rule_code' => 'AMT-001',
            'rule_name' => 'Large Amount',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertTrue($result['triggered']);
    }

    #[Test]
    public function test_amount_threshold_ignores_different_currency(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '100000',
            'currency_code' => 'USD',
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::AmountThreshold,
            'is_active' => true,
            'conditions' => [
                'min_amount' => '50000',
                'currency' => 'MYR', // Only applies to MYR
            ],
            'risk_score' => 40,
            'action' => 'flag',
            'rule_code' => 'AMT-002',
            'rule_name' => 'MYR Large Amount',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertFalse($result['triggered']);
    }

    #[Test]
    public function test_frequency_rule_triggers_on_rapid_transactions(): void
    {
        $customer = Customer::factory()->create();
        $now = now();

        // Create 10 transactions in the last hour (default max is 10, need 11 to trigger)
        for ($i = 0; $i < 10; $i++) {
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'created_at' => $now->copy()->subMinutes(rand(0, 55)),
            ]);
        }

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => $now,
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Frequency,
            'is_active' => true,
            'conditions' => [
                'window_hours' => 1,
                'max_transactions' => 10,
            ],
            'risk_score' => 55,
            'action' => 'flag',
            'rule_code' => 'FREQ-001',
            'rule_name' => 'Rapid Transactions',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertTrue($result['triggered']);
    }

    #[Test]
    public function test_geographic_rule_matches_high_risk_country(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'AFGHANISTAN',
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Geographic,
            'is_active' => true,
            'conditions' => [
                'countries' => ['AFGHANISTAN', 'NORTH KOREA'],
                'match_field' => 'customer_nationality',
            ],
            'risk_score' => 80,
            'action' => 'hold',
            'rule_code' => 'GEO-001',
            'rule_name' => 'High Risk Country',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertTrue($result['triggered']);
    }

    #[Test]
    public function test_geographic_rule_ignores_unknown_customer(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MALAYSIA',
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Geographic,
            'is_active' => true,
            'conditions' => [
                'countries' => ['AFGHANISTAN'],
                'match_field' => 'customer_nationality',
            ],
            'risk_score' => 80,
            'action' => 'hold',
            'rule_code' => 'GEO-002',
            'rule_name' => 'High Risk Country Only',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertFalse($result['triggered']);
    }

    #[Test]
    public function test_inactive_rule_never_triggers(): void
    {
        $customer = Customer::factory()->create();
        $transaction = Transaction::factory()->create(['customer_id' => $customer->id]);

        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Velocity,
            'is_active' => false,
            'conditions' => [],
            'risk_score' => 50,
            'action' => 'flag',
            'rule_code' => 'INACTIVE',
            'rule_name' => 'Inactive Rule',
        ]);

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertFalse($result['triggered']);
        $this->assertEquals(0, $result['risk_score']);
        $this->assertEquals('none', $result['action']);
    }

    #[Test]
    public function test_evaluation_error_returns_false_safely(): void
    {
        $customer = Customer::factory()->create();
        $transaction = Transaction::factory()->create(['customer_id' => $customer->id]);

        // Create a rule with invalid conditions that would cause an error
        $rule = new AmlRule([
            'rule_type' => AmlRuleType::Velocity,
            'is_active' => true,
            'conditions' => [
                'window_hours' => 'invalid', // Should be int, causes error
            ],
            'risk_score' => 50,
            'action' => 'flag',
            'rule_code' => 'ERROR',
            'rule_name' => 'Error Test',
        ]);

        // Mock the Log facade to verify error is logged
        \Log::shouldReceive('error')->once();

        $result = $this->evaluator->evaluate($transaction, $rule);

        $this->assertFalse($result['triggered']);
    }
}
