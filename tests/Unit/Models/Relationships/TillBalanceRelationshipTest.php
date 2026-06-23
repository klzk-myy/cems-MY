<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillBalanceRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_teller_allocation_and_counter(): void
    {
        $allocation = TellerAllocation::factory()->create();
        $till = TillBalance::factory()->create([
            'till_id' => Counter::factory(),
            'currency_code' => Currency::factory(),
            'branch_id' => Branch::factory(),
            'teller_allocation_id' => $allocation->id,
            'opened_by' => User::factory(),
        ]);

        $this->assertTrue($till->tellerAllocation->is($allocation));
    }
}
