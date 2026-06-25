<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_account_period_and_creator(): void
    {
        $period = AccountingPeriod::factory()->create();
        $budget = Budget::factory()->create([
            'account_code' => ChartOfAccount::factory(),
            'period_code' => $period->period_code,
            'created_by' => User::factory(),
        ]);

        $this->assertTrue($budget->period->is($period));
    }
}
