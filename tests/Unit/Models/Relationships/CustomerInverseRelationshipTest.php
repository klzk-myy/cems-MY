<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\SanctionsAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerInverseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_inverse_relationships(): void
    {
        $customer = Customer::factory()->create();

        CustomerBehavioralBaseline::factory()->create(['customer_id' => $customer->id]);
        CustomerRiskProfile::factory()->create(['customer_id' => $customer->id]);
        PepApprovalRequest::factory()->create(['customer_id' => $customer->id]);
        SanctionsAnalysis::factory()->create(['customer_id' => $customer->id]);

        $this->assertCount(1, $customer->behavioralBaselines);
        $this->assertCount(1, $customer->riskProfiles);
        $this->assertCount(1, $customer->pepApprovalRequests);
        $this->assertCount(1, $customer->sanctionsAnalyses);
    }
}
