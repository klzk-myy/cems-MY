<?php

namespace Tests\Unit;

use App\Enums\AmlRuleType;
use App\Enums\EddStatus;
use App\Enums\TransactionStatus;
use App\Models\AmlRule;
use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ComplianceService;
use App\Services\EddService;
use App\Services\EncryptionService;
use App\Services\MathService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fault Tests - Critical and High Severity Issues
 *
 * Tests for faults identified in fau.md:
 * - Fault #1: isRecordComplete() doesn't validate non-empty purpose_of_transaction
 */
class FaultAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected EddService $eddService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eddService = new EddService(new MathService);
    }

    /**
     * FAULT #1: isRecordComplete() allows empty purpose_of_transaction
     *
     * The isRecordComplete() method only checks for null, not empty string.
     * An EDD record with empty string for purpose_of_transaction passes validation.
     */
    public function test_edd_record_with_empty_purpose_of_transaction_is_not_complete(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        // Create EDD record with empty purpose_of_transaction
        $record = EnhancedDiligenceRecord::create([
            'customer_id' => $customer->id,
            'edd_reference' => 'EDD-202604-0001',
            'status' => EddStatus::Incomplete,
            'risk_level' => 'Medium',
            'source_of_funds' => 'Valid source of funds', // Non-empty
            'purpose_of_transaction' => '', // Empty string - should fail
        ]);

        $this->assertFalse($this->eddService->isRecordComplete($record));
    }

    /**
     * FAULT #1 FIX: isRecordComplete() should reject empty purpose_of_transaction
     */
    public function test_edd_record_requires_both_source_of_funds_and_purpose(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        // Record with non-empty source_of_funds but empty purpose
        $record1 = EnhancedDiligenceRecord::create([
            'customer_id' => $customer->id,
            'edd_reference' => 'EDD-202604-0002',
            'status' => EddStatus::Incomplete,
            'risk_level' => 'Medium',
            'source_of_funds' => 'Some funds',
            'purpose_of_transaction' => '', // Empty - should fail
        ]);

        // Record with empty source_of_funds but non-empty purpose
        $record2 = EnhancedDiligenceRecord::create([
            'customer_id' => $customer->id,
            'edd_reference' => 'EDD-202604-0003',
            'status' => EddStatus::Incomplete,
            'risk_level' => 'Medium',
            'source_of_funds' => '',
            'purpose_of_transaction' => 'Some purpose',
        ]);

        $this->assertFalse($this->eddService->isRecordComplete($record1));
        $this->assertFalse($this->eddService->isRecordComplete($record2));
    }

    /**
     * FAULT #1: Complete record should pass validation
     */
    public function test_complete_edd_record_passes_validation(): void
    {
        $customer = Customer::factory()->create();

        $record = EnhancedDiligenceRecord::create([
            'customer_id' => $customer->id,
            'edd_reference' => 'EDD-202604-0004',
            'status' => EddStatus::Incomplete,
            'risk_level' => 'Medium',
            'source_of_funds' => 'Valid source of funds',
            'purpose_of_transaction' => 'Valid purpose',
        ]);

        $this->assertTrue($this->eddService->isRecordComplete($record));
    }

    /**
     * FAULT #5: Sanctions screening LIKE wildcards not escaped
     *
     * If customer name contains % or _, they act as wildcards in SQL LIKE,
     * causing false matches in sanctions screening.
     */
    public function test_sanctions_query_escapes_wildcard_characters(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'John 100% Senior']);
        $list = SanctionList::factory()->create();

        // If wildcards are NOT escaped, '%" would match too broadly (e.g. "John 100X Senior").
        SanctionEntry::create([
            'list_id' => $list->id,
            'entity_name' => 'John 100X Senior',
            'aliases' => [],
            'details' => [],
        ]);

        $service = new ComplianceService(new EncryptionService, new MathService);
        $this->assertFalse($service->checkSanctionMatch($customer));

        // Exact-like match should still work
        SanctionEntry::create([
            'list_id' => $list->id,
            'entity_name' => 'John 100% Senior',
            'aliases' => [],
            'details' => [],
        ]);
        $this->assertTrue($service->checkSanctionMatch($customer));
    }

    /**
     * FAULT #10: countWorkingDays off-by-one error
     *
     * The while ($current->lt($to)) means exclusive of end date.
     * Monday to Tuesday = only Monday counted (should be 2 days).
     */
    public function test_working_days_calculation_inclusive_range(): void
    {
        $service = new ComplianceService(new EncryptionService, new MathService);

        $from = new Carbon('2026-04-13'); // Monday
        $to = new Carbon('2026-04-14');   // Tuesday

        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('countWorkingDays');
        $method->setAccessible(true);

        $days = $method->invoke($service, $from, $to);
        $this->assertEquals(2, $days);
    }

    /**
     * AML structuring evaluation must not use float math.
     *
     * With float math: 0.1 + 0.2001 can become 0.300099999..., which (at scale 4)
     * compares as 0.3000 and fails a 0.3001 threshold check.
     */
    public function test_aml_structuring_uses_precise_decimal_math(): void
    {
        $customer = Customer::factory()->create();

        $recent = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '0.1000',
            'status' => TransactionStatus::Completed,
        ]);

        $current = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '0.2001',
            'status' => TransactionStatus::Completed,
        ]);

        $rule = AmlRule::create([
            'rule_code' => 'STRUCT-TEST',
            'rule_name' => 'Structuring precision test',
            'rule_type' => AmlRuleType::Structuring->value,
            'conditions' => [
                'window_days' => 1,
                'min_transaction_count' => 2,
                'aggregate_threshold' => '0.3001',
            ],
            'action' => 'flag',
            'risk_score' => 10,
            'is_active' => true,
        ]);

        $result = $rule->evaluate($current);
        $this->assertTrue($result['triggered']);
        $this->assertSame('flag', $result['action']);
        $this->assertGreaterThan(0, $result['risk_score']);
    }

    /**
     * MathService::compare must correctly handle string amounts near thresholds.
     */
    public function test_math_service_comparison_precision(): void
    {
        $math = new MathService;

        // Test comparison at threshold boundaries
        $this->assertTrue($math->compare('50000.00', '50000.00') >= 0);
        $this->assertTrue($math->compare('50000.01', '50000.00') > 0);
        $this->assertTrue($math->compare('49999.99', '50000.00') < 0);

        // Test precision with small decimals
        $this->assertTrue($math->compare('0.3001', '0.3001') === 0);
        $this->assertTrue($math->compare('0.30009999', '0.3001') < 0);
    }
}
