<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Services\AuditService;
use App\Services\CustomerRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRelationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerRelationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerRelationService(
            $this->app->make(AuditService::class)
        );
    }

    public function test_add_relation_creates_relation_record(): void
    {
        $customer = Customer::factory()->create();

        $relation = $this->service->addRelation($customer->id, [
            'relation_type' => 'spouse',
            'related_name' => 'Jane Doe',
            'is_pep' => true,
        ]);

        $this->assertInstanceOf(CustomerRelation::class, $relation);
        $this->assertEquals('spouse', $relation->relation_type);
        $this->assertEquals('Jane Doe', $relation->related_name);
        $this->assertTrue($relation->is_pep);
        $this->assertEquals($customer->id, $relation->customer_id);
    }

    public function test_get_relations_returns_customer_relations(): void
    {
        $customer = Customer::factory()->create();
        CustomerRelation::factory()->count(3)->create([
            'customer_id' => $customer->id,
        ]);

        $relations = $this->service->getRelations($customer);

        $this->assertCount(3, $relations);
    }

    public function test_is_pep_associate_returns_true_when_pep_relation_exists(): void
    {
        $customer = Customer::factory()->create(['pep_status' => false]);
        CustomerRelation::factory()->create([
            'customer_id' => $customer->id,
            'is_pep' => true,
        ]);

        $this->assertTrue($this->service->isPepAssociate($customer));
    }

    public function test_is_pep_associate_returns_false_when_no_pep_relation(): void
    {
        $customer = Customer::factory()->create(['pep_status' => false]);

        $this->assertFalse($this->service->isPepAssociate($customer));
    }

    public function test_remove_relation_deletes_record(): void
    {
        $customer = Customer::factory()->create();
        $relation = CustomerRelation::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->service->removeRelation($relation->id);

        $this->assertNull(CustomerRelation::find($relation->id));
    }

    public function test_update_relation_updates_record(): void
    {
        $customer = Customer::factory()->create();
        $relation = CustomerRelation::factory()->create([
            'customer_id' => $customer->id,
            'related_name' => 'Original Name',
        ]);

        $updated = $this->service->updateRelation($relation->id, [
            'related_name' => 'Updated Name',
        ]);

        $this->assertEquals('Updated Name', $updated->related_name);
    }

    public function test_calculate_relation_risk_score_returns_zero_for_no_relations(): void
    {
        $customer = Customer::factory()->create(['pep_status' => false]);

        $score = $this->service->calculateRelationRiskScore($customer);

        $this->assertEquals(0, $score);
    }

    public function test_calculate_relation_risk_score_adds_pep_status(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);

        $score = $this->service->calculateRelationRiskScore($customer);

        $this->assertEquals(20, $score);
    }

    public function test_calculate_relation_risk_score_adds_pep_relations(): void
    {
        $customer = Customer::factory()->create(['pep_status' => false]);
        CustomerRelation::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'is_pep' => true,
        ]);

        $score = $this->service->calculateRelationRiskScore($customer);

        // 2 PEP relations: min(2*10, 10) = 10 (customer is not PEP so only relations count)
        $this->assertEquals(10, $score);
    }

    public function test_is_high_risk_relation_returns_true_when_pep_relation(): void
    {
        $customer = Customer::factory()->create();
        CustomerRelation::factory()->create([
            'customer_id' => $customer->id,
            'is_pep' => true,
        ]);

        $this->assertTrue($this->service->isHighRiskRelation($customer));
    }

    public function test_get_related_customers_returns_customer_models(): void
    {
        $customer = Customer::factory()->create();
        $relatedCustomer = Customer::factory()->create();
        CustomerRelation::factory()->create([
            'customer_id' => $customer->id,
            'related_customer_id' => $relatedCustomer->id,
        ]);

        $related = $this->service->getRelatedCustomers($customer);

        $this->assertCount(1, $related);
        $this->assertEquals($relatedCustomer->id, $related->first()->id);
    }
}
