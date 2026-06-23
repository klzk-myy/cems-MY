<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerNote;
use App\Models\CustomerRiskHistory;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->assertCount(2, $customer->transactions);
    }

    public function test_customer_has_notes_and_documents(): void
    {
        $customer = Customer::factory()->create();
        CustomerNote::factory()->count(2)->create(['customer_id' => $customer->id]);
        CustomerDocument::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->assertCount(2, $customer->notes);
        $this->assertCount(2, $customer->documents);
    }

    public function test_customer_has_risk_history(): void
    {
        $customer = Customer::factory()->create();
        CustomerRiskHistory::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->assertCount(2, $customer->riskHistory);
    }
}
