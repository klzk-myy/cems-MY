<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Customer;
use App\Models\CustomerRiskHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRiskHistoryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_customer_and_assessor(): void
    {
        $customer = Customer::factory()->create();
        $assessor = User::factory()->create();

        $history = CustomerRiskHistory::factory()->create([
            'customer_id' => $customer->id,
            'assessed_by' => $assessor->id,
        ]);

        $this->assertTrue($history->customer->is($customer));
        $this->assertTrue($history->assessor->is($assessor));
    }
}
